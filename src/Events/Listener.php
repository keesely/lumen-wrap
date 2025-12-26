<?php

namespace Lx\Events;

use Attribute;
use ReflectionClass;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_ALL)]
class Listener
{

  public function __construct(
    public $listener,
    public $method = null,
  ) {
    //
  }

  static function parseCallback($event) {
    $listeners = [];
    $reflection = new ReflectionClass($event);
    $attr = $reflection->getAttributes(Listener::class);
    if (count($attr) > 0) {
      foreach ($attr as $a) {
        $ins = $a->newInstance();
        if ($listener = $ins->listener) {
          if (
            (is_string($listener) && class_exists($listener))
            || is_callable($listener)
          ) {
            if ($ins->method && method_exists($listener, $ins->method)) {
              if (is_callable([$listener, $ins->method])) {
                $listener = fn($event) => call_user_func([$listener, $ins->method], $event);
              }
              else {
                $listener = [$listener, $ins->method];
              }
            }
            $listeners[] = $listener;

          }
        }
      }
    }
    return $listeners;
  }
}
