<?php

namespace Lx\Providers;

use Laravel\Lumen\Providers\EventServiceProvider as ServiceProvider;
use Lx\Events\Listener;
use ReflectionClass;

class EventServiceProvider extends ServiceProvider
{
  /**
   * The event listener mappings for the application.
   *
   * @var array
   */
  protected $listen = [];

  /**
   * The subscriber classes to register.
   *
   * @var array
   * */
  protected $subscribe = [];

  /**
   * Determine if events and listeners should be automatically discovered.
   * 
   * @var bool
   * */
  protected $shouldDiscover = false;

  /**
   * Determine if events and listeners should be automatically discovered.
   *
   * @return bool
   */
  public function shouldDiscoverEvents()
  {
    return $this->shouldDiscover;
  }

  public function register()
  {
    $this->app->singleton('events', function () {
      return new \Illuminate\Events\Dispatcher;
    });

    $this->registerListeners();
  }

  /**
   * Register the listeners for the provider.
   *
   * @return void
   * */
  protected function registerListeners()
  {
    foreach ($this->listen as $event => $listeners) {
      $key = $event;
      if (is_int($event)) {
        unset($this->listen[$key]);
        [$event, $this->listen[$event]] = [$listeners, []];
      }

      $reflection = new ReflectionClass($event);
      $attr = $reflection->getAttributes(Listener::class);
      if (count($attr) > 0) {
        $listeners = [];
        foreach ($attr as $a) {
          $listener = $a->newInstance()->listener;
          if ($listener && class_exists($listener)) {
            $listeners[] = $a->newInstance()->listener;
          }
        }

        $this->listen[$event] = array_unique(
          array_merge($this->listen[$event]?:[], $listeners)
        );
      }
    }
  }
}
