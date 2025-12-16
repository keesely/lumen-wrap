<?php

namespace Lx\Concerns;

use Closure;
use FastRoute\Dispatcher;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Lumen\Http\Request as LumenRequest;
use Laravel\Lumen\Routing\Closure as RoutingClosure;
use Laravel\Lumen\Routing\Controller as LumenController;
use Laravel\Lumen\Routing\Pipeline;
use Lx\Routing\RouteType;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use RuntimeException;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

trait RoutesRequests
{
    /**
     * All of the global middleware for the application.
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * All of the route specific middleware short-hands.
     *
     * @var array
     */
    protected $routeMiddleware = [];

    /**
     * The current route being dispatched.
     *
     * @var array
     */
    protected $currentRoute;

    /**
     * The FastRoute dispatcher.
     *
     * @var \FastRoute\Dispatcher
     */
    protected $dispatcher;

    /**
     * The Controller responser
     * @var \Illuminate\Http\Response
     * */
    protected $responser;

    /**
     * Add new middleware to the application.
     *
     * @param  \Closure|array  $middleware
     * @return $this
     */
    public function middleware($middleware)
    {
        if (! is_array($middleware)) {
            $middleware = [$middleware];
        }

        $this->middleware = array_unique(array_merge($this->middleware, $middleware));

        return $this;
    }

    /**
     * Define the route middleware for the application.
     *
     * @param  array  $middleware
     * @return $this
     */
    public function routeMiddleware(array $middleware)
    {
        $this->routeMiddleware = array_merge($this->routeMiddleware, $middleware);

        return $this;
    }

    /**
     * Dispatch request and return response.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function handle(SymfonyRequest $request)
    {
        $response = $this->dispatch($request);

        if (count($this->middleware) > 0) {
            $this->callTerminableMiddleware($response);
        }

        return $response;
    }

    /**
     * Run the application and send the response.
     *
     * @param  \Symfony\Component\HttpFoundation\Request|null  $request
     * @return void
     */
    public function run($request = null)
    {
        $response = $this->dispatch($request);

        if ($response instanceof SymfonyResponse) {
            $response->send();
        } else {
            echo (string) $response;
        }

        if (count($this->middleware) > 0) {
            $this->callTerminableMiddleware($response);
        }

        $this->app->terminate();
    }

    /**
     * Call the terminable middleware.
     *
     * @param  mixed  $response
     * @return void
     */
    protected function callTerminableMiddleware($response)
    {
        if ($this->shouldSkipMiddleware()) {
            return;
        }

        $response = $this->prepareResponse($response);

        foreach ($this->middleware as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            $instance = $this->make(explode(':', $middleware)[0]);

            if (method_exists($instance, 'terminate')) {
                $instance->terminate($this->make('request'), $response);
            }
        }
    }

    /**
     * Dispatch the incoming request.
     *
     * @param  \Symfony\Component\HttpFoundation\Request|null  $request
     * @return \Illuminate\Http\Response
     */
    public function dispatch($request = null)
    {
        [$method, $pathInfo] = $this->parseIncomingRequest($request);

        try {
            $this->boot();

            return $this->sendThroughPipeline($this->middleware, function ($request) use ($method, $pathInfo) {
                $this->instance(Request::class, $request);

                if (isset($this->router->getRoutes()[$method.$pathInfo])) {
                    return $this->handleFoundRoute([true, $this->router->getRoutes()[$method.$pathInfo]['action'], []]);
                }

                return $this->handleDispatcherResponse(
                    $this->createDispatcher()->dispatch($method, $pathInfo)
                );
            });
        } catch (Throwable $e) {
            return $this->prepareResponse($this->sendExceptionToHandler($e));
        }
    }

    /**
     * Parse the incoming request and return the method and path info.
     *
     * @param  \Symfony\Component\HttpFoundation\Request|null  $request
     * @return array
     */
    protected function parseIncomingRequest($request)
    {
        if (! $request) {
            $request = LumenRequest::capture();
        }

        $this->instance(Request::class, $this->prepareRequest($request));

        return [$request->getMethod(), '/'.trim($request->getPathInfo(), '/')];
    }

    /**
     * Create a FastRoute dispatcher instance for the application.
     *
     * @return \FastRoute\Dispatcher
     */
    protected function createDispatcher()
    {
        return $this->dispatcher ?: \FastRoute\simpleDispatcher(function ($r) {
            foreach ($this->router->getRoutes() as $route) {
                $r->addRoute($route['method'], $route['uri'], $route['action']);
            }
        });
    }

    /**
     * Set the FastRoute dispatcher instance.
     *
     * @param  \FastRoute\Dispatcher  $dispatcher
     * @return void
     */
    public function setDispatcher(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Handle the response from the FastRoute dispatcher.
     *
     * @param  array  $routeInfo
     * @return mixed
     */
    protected function handleDispatcherResponse($routeInfo)
    {
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                throw new NotFoundHttpException;
            case Dispatcher::METHOD_NOT_ALLOWED:
                throw new MethodNotAllowedHttpException($routeInfo[1]);
            case Dispatcher::FOUND:
                return $this->handleFoundRoute($routeInfo);
        }
    }

    /**
     * Handle a route found by the dispatcher.
     *
     * @param  array  $routeInfo
     * @return mixed
     */
    protected function handleFoundRoute($routeInfo)
    {
        $this->currentRoute = $routeInfo;

        $this['request']->setRouteResolver(function () {
            return $this->currentRoute;
        });

        $action = $routeInfo[1];

        // Pipe through route middleware...
        if (isset($action['middleware'])) {
            $middleware = $this->gatherMiddlewareClassNames($action['middleware']);

            return $this->prepareResponse($this->sendThroughPipeline($middleware, function () {
                return $this->callActionOnArrayBasedRoute($this['request']->route());
            }));
        }

        return $this->prepareResponse(
            $this->callActionOnArrayBasedRoute($routeInfo)
        );
    }

    /**
     * Call the Closure or invokable on the array based route.
     *
     * @param  array  $routeInfo
     * @return mixed
     */
    protected function callActionOnArrayBasedRoute($routeInfo)
    {
        $action = $routeInfo[1];

        if (isset($action['uses'])) {
            return $this->prepareResponse($this->callControllerAction($routeInfo));
        }

        foreach ($action as $value) {
            if ($value instanceof Closure) {
                $callable = $value->bindTo(new RoutingClosure);
                break;
            }

            if (is_object($value) && is_callable($value)) {
                $callable = $value;
                break;
            }
        }

        if (! isset($callable)) {
            throw new RuntimeException('Unable to resolve route handler.');
        }

        try {
            return $this->prepareResponse($this->call($callable, $routeInfo[2]));
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }
    }

    /**
     * Call a controller based route.
     *
     * @param  array  $routeInfo
     * @return mixed
     */
    protected function callControllerAction($routeInfo)
    {
        $uses = $routeInfo[1]['uses'];

        if (is_string($uses) && ! Str::contains($uses, '@')) {
            $uses .= '@__invoke';
        }

        [$controller, $method] = explode('@', $uses);

        if (! method_exists($instance = $this->make($controller), $method)) {
            throw new NotFoundHttpException;
        }

        if (method_exists($instance, 'getResponser') && $responser = $instance->getResponser()) {
          $this->responser = $responser;
        }

        if ($instance instanceof LumenController) {
            return $this->callLumenController($instance, $method, $routeInfo);
        } else {
            return $this->callControllerCallable(
                [$instance, $method], $routeInfo[2]
            );
        }
    }

    /**
     * Send the request through a Lumen controller.
     *
     * @param  mixed  $instance
     * @param  string  $method
     * @param  array  $routeInfo
     * @return mixed
     */
    protected function callLumenController($instance, $method, $routeInfo)
    {
        $middleware = $instance->getMiddlewareForMethod($method);

        if (count($middleware) > 0) {
            return $this->callLumenControllerWithMiddleware(
                $instance, $method, $routeInfo, $middleware
            );
        } else {
            return $this->callControllerCallable(
                [$instance, $method], $routeInfo[2]
            );
        }
    }

    /**
     * Send the request through a set of controller middleware.
     *
     * @param  mixed  $instance
     * @param  string  $method
     * @param  array  $routeInfo
     * @param  array  $middleware
     * @return mixed
     */
    protected function callLumenControllerWithMiddleware($instance, $method, $routeInfo, $middleware)
    {
        $middleware = $this->gatherMiddlewareClassNames($middleware);

        return $this->sendThroughPipeline($middleware, function () use ($instance, $method, $routeInfo) {
            return $this->callControllerCallable([$instance, $method], $routeInfo[2]);
        });
    }

    /**
     * Call a controller callable and return the response.
     *
     * @param  callable  $callable
     * @param  array  $parameters
     * @return \Illuminate\Http\Response
     */
    protected function callControllerCallable(callable $callable, array $parameters = [])
    {
      try {
        // @Change add $parameters parse prepare
        $parameters = $this->parseParameters($parameters, $callable);

        return $this->prepareResponse(
          $this->call($callable, $parameters)
        );
      } catch (HttpResponseException $e) {
        return $e->getResponse();
      }
    }

    /**
     * Gather the full class names for the middleware short-cut string.
     *
     * @param  string|array  $middleware
     * @return array
     */
    protected function gatherMiddlewareClassNames($middleware)
    {
        $middleware = is_string($middleware) ? explode('|', $middleware) : (array) $middleware;

        return array_map(function ($name) {
            [$name, $parameters] = array_pad(explode(':', $name, 2), 2, null);

            return Arr::get($this->routeMiddleware, $name, $name).($parameters ? ':'.$parameters : '');
        }, $middleware);
    }

    /**
     * Send the request through the pipeline with the given callback.
     *
     * @param  array  $middleware
     * @param  \Closure  $then
     * @return mixed
     */
    protected function sendThroughPipeline(array $middleware, Closure $then)
    {
        if (count($middleware) > 0 && ! $this->shouldSkipMiddleware()) {
            return (new Pipeline($this))
                ->send($this->make('request'))
                ->through($middleware)
                ->then($then);
        }

        return $then($this->make('request'));
    }

    /**
     * Prepare the response for sending.
     *
     * @param  mixed  $response
     * @return \Illuminate\Http\Response
     */
    public function prepareResponse($response)
    {
        $request = app(Request::class);

        if (($this->responser instanceof \Closure) || is_array($this->responser)) {
          $response = $this->forwardToResponser($response);
        }

        if ($response instanceof Responsable) {
            $response = $response->toResponse($request);
        }
        elseif ($response instanceof Closure) {
            $response = $response($request);
        }

        if ($response instanceof PsrResponseInterface) {
            $response = (new HttpFoundationFactory)->createResponse($response);
        } elseif (! $response instanceof SymfonyResponse) {
            $response = new Response($response);
        } elseif ($response instanceof BinaryFileResponse) {
            $response = $response->prepare(Request::capture());
        }

        return $response->prepare($request);
    }

    /**
     * forwardToResponser
     * 
     * @param mixed $response
     *
     * @return mixed
     * */
    protected function forwardToResponser($response) {
      $resers = is_array($this->responser) ? $this->responser : [$this->responser];
      $request = $this->app->make('request');
      foreach ($resers as $res) {
        if (is_callable($res)) {
          $response = $res($response, $request);
        } 
      }
      return $response;
    }

    /**
     * Determines whether middleware should be skipped during request.
     *
     * @return bool
     */
    protected function shouldSkipMiddleware()
    {
        return $this->bound('middleware.disable') && $this->make('middleware.disable') === true;
    }

    /**
     * @Feature Add parseParameters
     * */
    protected function parseParameters(array &$parameters, callable $callable)
    {
      @[$controller, $method] = $callable;
      $args = new \ReflectionMethod($controller, $method);
      foreach ($args->getParameters() as $param) {
        $name = $param->getName();
        if (!isset($parameters[$name])) continue;
        if ($param->hasType()) {
          $ptype = $param->getType();
          $pname = $ptype->getName();
          if ($ptype->isBuiltin()) {
            if ($ptype->getName() == 'array')
              $parameters[$name] = (array) $parameters[$name];
          }
          else if ($ptype instanceof \ReflectionNamedType) {
            if (!class_exists($pname)) {
              class_alias(RouteType::class, $pname);
              $parameters[$name] = new $pname($parameters[$name], $ptype);
            }
            else if (($p = new $pname) instanceof Model) {
              $find = method_exists($p, 'indexOf') ? 'indexOf' : 'findOrFail';
              if (!$parameters[$name] = $pname::$find($parameters[$name]))
                throw new ModelNotFoundException;
            }
            else $parameters[$name] = new $pname($parameters[$name]);
          }
        }
      }
      return $parameters;
    }
}
