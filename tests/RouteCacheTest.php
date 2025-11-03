<?php

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class RouteCacheTest extends TestCase
{

  public function testRouteCache()
  {
    $oriApp = clone $this->app;
    $oriApp->router = null;
    $originalRouter = $oriApp->router = $oriApp->instance('router', new Lx\Router($oriApp));

    $this->assertFalse($originalRouter->isLoaded());

    $command = 'route:cache';
    $kernel = $oriApp[Laravel\Lumen\Console\Kernel::class];
    $res = $kernel->call($command, []);

    $router = $oriApp->bootstrapRouter()->router;
    if ($router->isCached()) $router = $router->refresh();

    $this->assertTrue($router->isCached(), 'Router is cached');
    $this->assertTrue($router->isLoaded(), 'Router is loaded');
  }

  public function testRouteCacheClear()
  {
    $oriApp = clone $this->app;
    $oriApp->router = null;
    $originalRouter = $oriApp->router = $oriApp->instance('router', new Lx\Router($oriApp));

    $this->assertFalse($originalRouter->isLoaded());

    $command = 'route:cache';
    $kernel = $oriApp[Laravel\Lumen\Console\Kernel::class];
    $res = $kernel->call($command, ['op' => 'clear']);

    $router = $oriApp->bootstrapRouter()->router;
    if ($router->isCached()) $router = $router->refresh();

    $this->assertFalse($router->isCached(), 'Router is cached');
    $this->assertFalse($router->isLoaded(), 'Router is loaded');
  }
}
