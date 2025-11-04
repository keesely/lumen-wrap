<?php

namespace Lx;

use Laravel\Lumen\Application;
use Laravel\Lumen\Bootstrap\LoadEnvironmentVariables;

class Bootstrap extends Application {

  use Concerns\RoutesRequests;

  public function __construct($basePath = null) {
    $this->loadEnvironmentVariables($basePath);
    parent::__construct($basePath);

    return $this->bootstrap();
  }

  /**
   * Bootstrap the application.
   * @return Bootstrap
   * */
  public function bootstrap() {
    $this->loadConfigure(['app', 'database']);
    $this->aliases(config('app.aliases', []));
    date_default_timezone_set(config('app.timezone', 'UTC'));

    // load configure from config/app.php
    $this->loadConfigure(config('app.configure', []));
    // register providers
    $this->registerProviders(config('app.providers', []));
    // register middlewares && route middlewares
    $this->app->middleware(config('app.middleware', []));
    $this->app->routeMiddleware(config('app.routeMiddleware', []));
    return $this;
  }

  /**
   * Load the environment variables.
   * @return Bootstrap
   * */
  protected function loadEnvironmentVariables($envPath = null) {
    //(new Bootstrap\LoadEnvironmentVariables($envPath ?: $this->basePath))->bootstrap();
    (new LoadEnvironmentVariables($envPath ?: $this->basePath))->bootstrap();
    return $this;
  }

  /**
   * Load the configure.
   * @param array $configure
   * @return Bootstrap
   * */
  protected function loadConfigure(array $configure) {
    $config = $this->app['config'] ?: $this->app->make('config');

    foreach ($configure as $name => $conf) {
      if (is_array($conf)) {
        if (is_numeric($name)) {
          foreach ($conf as $key => $value) $config->set($key, $value);
        }
        else $config->set($name, $conf);
      }
      else if ($path = $this->getConfigurationPath($conf)) $config->set($conf, require $path);
      else $this->app->configure($conf);
    }
    return $this;
  }

  protected function registerProviders(array $providers) {
    foreach ($providers as $provider) $this->app->register($provider);
    return $this;
  }

  public function version() {
    return 'Lumen (11.1.0) (Laravel Components ^11.0) (Lw ^11.0)';
  }

  /**
   * Bootstrap the router instance.
   * @return void
   * */
  public function bootstrapRouter() {
    if ($router = $this['router'] ?? null) $this->router = $router;
    else $this->router = $this->instance('router', new Router($this));

    // is cached routes?
    if ($this->router->isLoaded()) return $this;
    if ($this->router->isCached()) $this->router->loadCachedRoutes();

    return $this;
  }

  /**
   * ----------------------------------------------------
   * Register and Bind
   * ----------------------------------------------------
   * */
  public function withCookie() {
    $this->app->register(\Illuminate\Cookie\CookieServiceProvider::class);
    $this->app->bind(\Illuminate\Contracts\Cookie\QueueingFactory::class, 'cookie');
    $this->app->middleware([
      \Illuminate\Cookie\Middleware\EncryptCookies::class,
      \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    ]);
    return $this;
  }

  public function withSession() {
    $this->configure('session');
    $this->app->register(\Illuminate\Session\SessionServiceProvider::class);
    $this->app->bind(\Illuminate\Session\SessionManager::class, function ($app) {
      return new \Illuminate\Session\SessionManager($app);
    });
    $this->app->middleware(\Illuminate\Session\Middleware\StartSession::class);
    return $this;
  }

  public function withAuth() {
    $this->configure('auth');
    $this->app->bind(\Illuminate\Auth\AuthManager::class, function ($app) {
      return new \Illuminate\Auth\AuthManager($app);
    });
    return $this;
  }

  public function withFilesystem() {
    $this->configure('filesystems');
    $this->app->bind(\Illuminate\Contracts\Filesystem\Factory::class, function ($app) {
      return new \Illuminate\Filesystem\FilesystemManager($app);
    });
    $this->app->singleton('filesystem', function ($app) {
      return $app->loadComponent(
        'filesystems',
        \Illuminate\Filesystem\FilesystemServiceProvider::class,
        'filesystem'
      );
    });
    return $this;
  }

  public function withCache() {
    $this->configure('cache');
    $this->app->bind(\Illuminate\Cache\CacheManager::class, function ($app) {
      return new \Illuminate\Cache\CacheManager($app);
    });
    return $this;
  }

  public function withResponse() {
    $this->app->singleton(\Illuminate\Contracts\Routing\ResponseFactory::class, function ($app) {
      return new \Illuminate\Routing\ResponseFactory(
        $app['Illuminate\Contracts\View\Factory'], 
        $app['Illuminate\Routing\Redirector']
      );
    });
  }

  public function with(string|array|callable...$providers) {
    foreach ($providers as $provider) {
      if (is_string($provider)) {
        $method = 'with' . ucfirst($provider);
        if (method_exists($this, $method)) $this->$method();
        else if (class_exists($provider)) $this->app->register($provider);
      }

      else if (is_callable($provider)) $provider($this);
      else if (is_array($provider)) {
        foreach ($provider as $p) $this->app->register($p);
      }
    }
    return $this;
  }

  public function aliases(array $aliases) {
    $this->app->withFacades(true, array_flip($aliases));
    return $this;
  }

  public function singletons(array $singletons) {
    foreach ($singletons as $singleton) {
      $this->app->singleton(...$singleton);
    }
    return $this;
  }

  public function tap(callable $callback) {
    return tap($this, $callback);
  }

  /***
   * ----------------------------------------------------
   * Register and Bind End./
   * ----------------------------------------------------
   * */

  public function __call($name, $args) {
    if (method_exists($this->app, $name))
      return call_user_func_array([$this->app, $name], $args);

    return $this->app->make($name, $args);
  }
  
}

