<?php

defined('BASE_PATH') || define('BASE_PATH', realpath(dirname(__DIR__)));
defined('VENDOR_PATH') || define('VENDOR_PATH', BASE_PATH . '/vendor/');

/*
|--------------------------------------------------------------------------
| Register The Composer Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader
| for our application. We just need to utilize it! We'll require it
| into the script here so that we do not have to worry about the
| loading of any our classes "manually". Feels great to relax.
|
*/
require_once VENDOR_PATH . '/autoload.php';

(new Laravel\Lumen\Bootstrap\LoadEnvironmentVariables(BASE_PATH))->bootstrap();

/*
|--------------------------------------------------------------------------
| Set The Default Timezone
|--------------------------------------------------------------------------
|
| Here we will set the default timezone for PHP. PHP is notoriously mean
| if the timezone is not explicitly set. This will be used by each of
| the PHP date and date-time functions throughout the application.
|
*/

date_default_timezone_set('UTC');

Carbon\Carbon::setTestNow(Carbon\Carbon::now());

/**
 * public Bootstrap (AppPath, envPath) initialize
 * */
$app = new Lx\Bootstrap(BASE_PATH);

/*
|--------------------------------------------------------------------------
| Register Container Bindings
|--------------------------------------------------------------------------
|
| Now we will register a few bindings in the service container. We will
| register the exception handler and the console kernel. You may add
| your own bindings here if you like or you can make another file.
|
 */

// $app->singleton(
//   Illuminate\Contracts\Debug\ExceptionHandler::class,
//   App\Exceptions\Handler::class
// );
// 
// $app->singleton(
//   Illuminate\Contracts\Console\Kernel::class,
//   App\Console\Kernel::class
// );

/*
|--------------------------------------------------------------------------
| Register Config Files
|--------------------------------------------------------------------------
|
| Now we will register the "app" configuration file. If the file exists in
| your configuration directory it will be loaded; otherwise, we'll load
| the default version. You may register other files below as needed.
|
 */

$app->configure('app');

$app->singleton(
  Illuminate\Contracts\Debug\ExceptionHandler::class,
  Laravel\Lumen\Exceptions\Handler::class
);
$app->singleton(
  Illuminate\Contracts\Console\Kernel::class,
  Laravel\Lumen\Console\Kernel::class
);

$app->tap(fn($app) => $app->router->loadRoutes([
  'routes' => 'config/routes.php',
]));

$app->tap(fn($app) => $app->register(Lx\Commands\CommandsServiceProvider::class));


//$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

return $app;
