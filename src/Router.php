<?php

namespace Lx;

use Laravel\Lumen\Routing\Router as LumenRouter;
use Laravel\Lumen\Routing\Controller as BaseController;
use Laravel\SerializableClosure\SerializableClosure;
use Illuminate\Support\Arr;
use Exception as RouterException;

class Router extends LumenRouter {

  static public $_routes = [];

  protected $domain = 0;

  protected $domainStack = [];

  protected $regexAliases = [];

  protected $loaded = false;

  const DEFAULT_CONTROLLER_ACTIONS = [
    '@index'   => ['GET'   , '/'],
    '@create'  => ['POST'  , '/'],
    '@show'    => ['GET'   , '/{id}'],
    '@update'  => ['PUT'   , '/{id}'],
    '@patch'   => ['PATCH' , '/{id}'],
    '@destroy' => ['DELETE', '/{id}'],
  ];

  const HTTP_METHODS = [
    'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS', 'TRACE',
  ];

  /**
   * parse controller actions
   * */
  protected function getControllerActions($controller): array {
    if ($this->hasGroupStack()) {
      $attributes = $this->mergeWithLastGroup([]);
      if (isset($attributes['namespace'])) {
        $controller = '\\'.$attributes['namespace'] . '\\' . $controller;
      }
    }
    //throw new RouterException('Controller ('.$controller.') not found');
    if (!class_exists($controller))  return [$controller, []];

    // get controller methods
    $methods = get_class_methods($controller);

    // 排除特殊方法 & 私有方法 & 通用方法
    $baseControllerMethods = get_class_methods(BaseController::class);
    $methods = array_filter($methods, function($method) use ($baseControllerMethods) {
      if (strpos($method, '_') !== false) return false;
      return !in_array($method, $baseControllerMethods);
    });
    return [$controller, $methods];
  }

  /**
   * parse Controller action parameters
   * */
  protected function parseActionParmsTypes(\ReflectionMethod $method): array {
    $params = array_map(function ($param) {
      $name = $param->getName();
      $isNullable = $param->isDefaultValueAvailable();

      // 无类型声明
      if (!$type = $param->getType()) return '{'.$name.'}';

      // 存在默认值参数则为可选
      if ($isNullable) return '[{'.$name.'}]'; 

      // 混合类型
      if ($type instanceof \ReflectionUnionType) {
        $types = $param->getType()?->getTypes() ?: null;
        if (!$types || count($types) > 1) return '{'.$name.'}';
        $type = $types[0]->getName();
      }

      // 具名类型 && 非内置
      else if ($type instanceof \ReflectionNamedType) {
        if (!$type->isBuiltin()) {
          if (!class_exists($type->getName())) $type = basename(str_replace('\\', '/', $type->getName()));
          else {
            if ($type->getName() == 'Illuminate\Http\Request') return null;
            if ((@new ('\\'.$type->getName())) instanceof \Illuminate\Http\Request) return null;
            $type = null;
          }
        }
        else $type = $type->getName();
      }

      if ($type) $type = $this->paramType2RouterType($type);

      return $type ? '{'.$name.':'.$type.'}' : '{'.$name.'}';
    }, $method->getParameters());
    $params = array_filter($params);
    return $params;
  }

  /**
   * get uri regex alias names
   * @return array
   * */
  public function getRegexAlias (): array {
    if (!$this->regexAliases || !count($this->regexAliases)) {
      $this->regexAliases = ($this->app->make('config')->get('app.routes.regexAlias', [
        'id' => '\d+',
      ]));
    }
    return $this->regexAliases ?: [];
  }

  public function setRegexAlias (array $regexAlias) {
    $this->regexAliases = array_merge($this->getRegexAlias(), $regexAlias);
    return $this->regexAliases;
  }

  public function resetRegexAlias () {
    $this->regexAliases = null;
  }


  // 类型转换
  protected function paramType2RouterType($type) {

    switch ($type) {
    // 是否内置类型
    case 'int':
    case 'integer':
    case 'id':
      return '\d+';
    case 'string':
      return '[^\/]+';
    case 'uuid':
      return '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
    case 'path':
      return '[^\/].+';
    default:
      // 自定义类型
      $regxAlias = $this->getRegexAlias();
      if (is_array($regxAlias) && isset($regxAlias[$type])) {
        return $regxAlias[$type];
      }
      return null;
    }
  }

  /**
   * parse controller action
   * */
  protected function parseControllerAction(&$controller, $action): array {
    if (!$controller instanceof \ReflectionClass) {
      $controller = new \ReflectionClass($controller);
    }

    $method = $controller->getMethod($action);
    $params = $this->parseActionParmsTypes($method);

    $actionName = $action;
    // 驼峰转下划线
    if (strpos($actionName, '_') === false) {
      $actionName = strtolower(preg_replace('/([A-Z])/', '_$1', $actionName));
      $actionName = strtolower($actionName);
      if ($actionName[0] == '_') $actionName = substr($actionName, 1);
    }
    $actionName = explode('_', $actionName);
    $method = strtoupper($actionName[0]);
    if (in_array($method, ['ANY', 'ALL'])) array_shift($actionName) && $method = static::HTTP_METHODS;
    else if (!in_array($method, self::HTTP_METHODS)) $method = ['GET'];
    else array_shift($actionName) && ($method = [$method]);
    if (!in_array('by', $actionName)) return [$method, implode('/', $actionName)];

    $len = count($actionName) -1;
    foreach ($actionName as $i => &$name) {
      if ($name == 'by' && $i < $len) {
        $param = array_shift($params);
        $name = $param;
      }
      else if ($name == 'by' && $i == $len) {
        $name = implode('/', $params);
      }
    }
    return [$method, implode('/', $actionName)];
  }
  
  public function controller ($uri, $controller, array $options = []) {
    [$_controller, $actions] = $this->getControllerActions($controller);

    $regexAlias = null;
    if (isset($options['regexAlias']) && ($regexAlias = $options['regexAlias'] ?: [])) {
      $regexAlias = $this->setRegexAlias($regexAlias);
    }

    $defaultActions = static::DEFAULT_CONTROLLER_ACTIONS;
    foreach ($actions as $action) {
      if (isset($defaultActions['@'.$action])) {
        $action = '@'.$action;
        @[$method, $_uri] = $defaultActions[$action];
        if ($method && $_uri) $this->addRoute($method, $uri .$_uri, $controller.$action);
        continue;
      }
      @[$method, $_uri] = $this->parseControllerAction($_controller, $action);
      if (is_array($method) && is_string($_uri)) {
        [$action, $_uri] = ['@'.$action, '/'.$_uri];
        foreach ($method as $m) {
          $this->addRoute($m, $uri.$_uri, $controller.$action);
        }
      }
    }
    if (!is_null($regexAlias)) $this->resetRegexAlias();
    return $this;
  }

  public function any ($uri, $action) {
    foreach (static::HTTP_METHODS as $verb) {
      $this->addRoute($verb, $uri, $action);
    }
    return $this;
  }

  public function mix ($uri, $action) {
    $attributes = [];
    if ($this->hasGroupStack()) {
      $attributes = $this->mergeWithLastGroup([]);
    }
    if (isset($attributes['namespace'])) {
      $controller = '\\'.$attributes['namespace'] . '\\' . $action;
    }

    if (class_exists($controller) && method_exists($controller, 'getRoutes')) {
      $routes = $controller::getRoutes();
      if (is_array($routes)) {
        foreach ($routes as $route) {
          @list($m, $u, $c, $a) = $route;
          $u = $uri . $u;
          $c = $action . '@' . $a;
          $this->addRoute($m, $u, $c);
        }
      }
    }
    return $this;
  }

  public function addRoute ($method, $uri, $action) {
    $action = $this->parseAction($action);

    $attributes = null;

    if ($this->hasGroupStack()) {
      $attributes = $this->mergeWithLastGroup([]);
    }

    if (isset($attributes) && is_array($attributes)) {
      if (isset($attributes['prefix'])) {
        $uri = trim($attributes['prefix'], '/').'/'.trim($uri, '/');
      }

      if (isset($attributes['suffix'])) {
        $uri = trim($uri, '/').rtrim($attributes['suffix'], '/');
      }

      $action = $this->mergeGroupAttributes($action, $attributes);
    }

    $uri = '/'.trim($uri, '/');

    if (isset($action['as'])) {
      $this->namedRoutes[$action['as']] = $uri;
    }
    if (!$this->domain) {
      $this->domain = 0;
    }

    if (is_array($method)) {
      foreach ($method as $verb) {
        self::$_routes[$this->domain][$verb.$uri] = 
          $this->routes[$verb.$uri] = ['method' => $verb, 'uri' => $uri, 'action' => $action];
      }
    } else {
      self::$_routes[$this->domain][$method.$uri] = 
        $this->routes[$method.$uri] = ['method' => $method, 'uri' => $uri, 'action' => $action];
    }
    return $this;
    //self::$_routes = $this->routes;
  }

  protected function _getRoutes () {
    $domain = $this->server_host()[1];
    $routes = self::$_routes; // ?: $this->routes;

    foreach ($routes as $host => $router) {
      if (preg_match('/^'.$host.'$/si', $domain)) {
        return $router;
      }
    }
    return $routes[0] ?? $routes;
  }

  public function getRoutes () {
    return $this->routes = $this->_getRoutes();
  }

  public function domain ($domain, $action) {
    $std = new \FastRoute\RouteParser\Std($domain);
    $parse = $std->parse($domain);
    $domain = $this->server_host()[1];
    
    $regxstr = '';
    $dataKeys = [];
    foreach ($parse[0] as $regx) {
      if (is_array($regx)) {
        $regx[1] = str_replace('/', '.', $regx[1]);
        $regxstr .='('.$regx[1].')';
        $dataKeys[] = $regx[0];
      } else {
        $regxstr .= $regx;
      }
    }
    $data = false;
    preg_replace_callback("/^{$regxstr}$/si", function($match) use(&$data, $dataKeys) {
      $data = [];
      for ($i = 1; $i < count($match); $i++) {
        if (Arr::get($match, $i) && Arr::get($dataKeys, $i-1)) {
          $data[$dataKeys[$i-1]] = $match[$i];
        }
      }
    }, $domain);

    if (false !== $data) {
      $this->domainStack[$domain] = $data;
      $this->domain = $regxstr;

      call_user_func($action, $this);
      $this->domain = NULL;
    }
  }

  public function server_host () :array {
    $s = $_SERVER;
    $host = Arr::get($s, 'HTTP_HOST', Arr::get($s, 'SERVER_NAME'));
    $port = Arr::get($s, 'SERVER_PORT', 80);

    $https = Arr::get($s, 'HTTPS');
    $protocol = (!$https && $https !== 'off' || $port == 443) ? 'https' : 'http';
    return [$protocol, $host, $port];
  }

  public function getDomainParams () {
    return Arr::get($this->domainStack, $this->server_host()[1], []);
  }

  public function getDomainParam ($name, $default = null) {
    return Arr::get($this->getDomainParams(), $name, $default);
  }

  /**
   * Register a set of routes with a set of shared attributes.
   *
   * @param  array  $attributes
   * @param  \Closure  $callback
   * @return void
   *
   * @Change $callback is default null is deprecated, the explicit routes should be defined in $attributes
   */
  public function group(array $attributes, \Closure $callback) {
    if (isset($attributes['middleware']) && is_string($attributes['middleware'])) {
      $attributes['middleware'] = explode('|', $attributes['middleware']);
    }

    // @Change $callback is default null and attributes has routes 
    if ($callback === null && isset($attributes['routes'])) {
      $routes = $attributes['routes'];
      if (is_string($routes)) {
        $routes = realpath($routes) ?: base_path($routes);
        if (file_exists($routes)) {
          $callback = function ($router) use ($routes) {
            require $routes;
          };
        }
      }
    }

    $this->updateGroupStack($attributes);

    if (is_callable($callback)) $callback($this);

    array_pop($this->groupStack);
  }

  public function setting (array $attributes) {
    $routes = $attributes['routes'] ?? null;

    $callback = fn($router) => abort(404, 'Not Found');

    if($routes) {
      $routes = is_array($routes) ? $routes : [$routes];
      
      $routes = array_map(fn($f) => $this->app->basePath($f), $routes);
      $reqRoutes = function ($router, $routes) {
        foreach ($routes as $route) {
          file_exists($route) && require $route;
        }
      };
      $callback = fn($router) => $reqRoutes($router, $routes);
    }
    return $this->group($attributes, $callback);
  }

  public function getCachedRoutesPath() {
    return $this->app->storagePath('app/cache/routes.php');
  }

  public function isCached () {
    return file_exists($this->getCachedRoutesPath());
  }

  public function isLoaded () {
    return $this->loaded;
  }

  public function loadCachedRoutes () {
    if ($this->loaded) return $this;
    $router = $this;
    require $this->getCachedRoutesPath();
  }

  public function setCompiledRoutes(array $routes) {
    $closures = $routes['closures'] ?? function () { return []; };
    $closures = unserialize($closures)->getClosure();

    Routing\RouteClosure::setClosures($closures());
    $routes = $routes['routes'] ?? $routes;
    $this->routes = $routes;
    $this->loaded = true;
    return $this;
  }

  public function refresh() {
    $this->loaded = false;
    $this->routes = [];
    $this->loadCachedRoutes();
    return $this;
  }

}
