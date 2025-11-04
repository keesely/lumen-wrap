<?php

namespace Lx\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\RouteCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Laravel\SerializableClosure\SerializableClosure;

#[AsCommand(name: 'route:cache')]
class RouteCacheCommand extends Command
{

  protected $signature = 'route:cache {op=cache}';
  /**
   * The console command name.
   *
   * @var string
   */
  protected $name = 'route:cache';

  /**
   * The name of the console command.
   *
   * This name is used to identify the command during lazy loading.
   *
   * @var string|null
   *
   * @deprecated
   */
  protected static $defaultName = 'route:cache';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Create a route cache file for faster route registration';

  /**
   * The filesystem instance.
   *
   * @var \Illuminate\Filesystem\Filesystem
   */
  protected $files;

  /**
   * Create a new route command instance.
   *
   * @param  \Illuminate\Filesystem\Filesystem  $files
   * @return void
   */
  public function __construct(Filesystem $files)
  {
    parent::__construct();

    $this->files = $files;
  }

  /**
   * Execute the console command.
   *
   * @return void
   */
  public function handle()
  {
    if (!$router = $this->laravel['router'] ?? null) {
      return $this->error('Router not found.');
    }

    if ($router->isCached()) $this->files->delete($router->getCachedRoutesPath());
    if ('clear' === $this->argument('op')) {
      return $this->info('Route cache cleared successfully.');
    }

    $routes = $this->getFreshApplicationRoutes();

    if (count($routes) === 0) {
      return $this->error("Your application doesn't have any routes.");
    }

    // if path is not writable
    $this->files->ensureDirectoryExists(dirname($router->getCachedRoutesPath()));

    $this->files->put(
      $router->getCachedRoutesPath(), 
      $this->buildRouteCacheFile($routes)
    );

    $this->info('Routes cached successfully.');
  }

  /**
   * Boot a fresh copy of the application and get the routes.
   *
   * @return \Illuminate\Routing\RouteCollection
   */
  protected function getFreshApplicationRoutes()
  {
    // return $routes;

    return tap($this->getFreshApplication()['router']->getRoutes(), function (&$routes) {
      $closures = [];
      foreach ($routes as &$route) {
        $action = &$route['action'];
        if ($closure = $action[0] ?? false) {
          $serialized = serialize(new SerializableClosure($closure));
          $hash = hash('sha256', $serialized);
          $closures[$hash] = $closure;
          unset($action[0]);
          $action['hash'] = $hash;
          $action['uses'] = '\Lx\Routing\RouteClosure@closure';
        }
      }
      $closures = serialize(new SerializableClosure(function () use($closures) {
        return $closures;
      }));
      $routes = ['routes' => $routes, 'closures' => $closures];
    });
  }

  /**
   * Get a fresh application instance.
   *
   * @return \Illuminate\Contracts\Foundation\Application
   */
  protected function getFreshApplication()
  {
    return tap(
      require $this->laravel->basePath('bootstrap/app.php'),
      fn($app) => $app->make(ConsoleKernelContract::class)->bootstrap()
    );
  }

  /**
   * Build the route cache file.
   *
   * @param  \Illuminate\Routing\RouteCollection  $routes
   * @return string
   */
  protected function buildRouteCacheFile(array $routes)
  {
    $stub = $this->files->get(__DIR__.'/stubs/routes.stub');

    return str_replace('{{routes}}', var_export($routes, true), $stub);
  }
}
