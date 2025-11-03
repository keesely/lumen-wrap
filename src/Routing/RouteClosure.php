<?php

namespace Lx\Routing;

use Closure;
use Laravel\Lumen\Routing\Closure as RoutingClosure;
use Illuminate\Container\BoundMethod;
use Illuminate\Container\Container;

class RouteClosure {

  static $closures = [];

  protected $hash;

  static function setClosures(array $closures) {
    static::$closures = $closures;
  }

  public function closure(...$args) {
    $routeInfo = request()->route();
    $action = $routeInfo[1];
    $hash = $action['hash'] ?? '';
    if (!$callable = static::$closures[$hash] ?? false) return abort(404);
    return (new BoundMethod)->call(Container::getInstance(), $callable, $routeInfo[2]);
  }

}
