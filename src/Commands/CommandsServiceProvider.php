<?php

namespace Lx\Commands;

use Illuminate\Support\ServiceProvider;
use Lx\Commands\ConsoleMakeCommand;

class CommandsServiceProvider extends ServiceProvider {

    // @Change Extract dev Commands
    protected $extCommands = [
      'ControllerMake' => 'command.controller.make',
      'ConsoleMake'    => 'command.console.make',
      'ExceptionMake'  => 'command.exception.make',
      'JobMake'        => 'command.job.make',
      'KeyGenerate'    => 'command.key.generate',
      'MiddlewareMake' => 'command.middleware.make',
      'ModelMake'      => 'command.model.make',
      'MigrateTables'  => 'command.migrate.tables',
      //'VendorPublish'   => 'command.vendor.publish',
    ];

    /**
     * Register the given commands.
     *
     * @param  array  $commands
     * @return void
     */
    protected function registerCommands() {
        // @Change Extract dev Commands Running
        if ($this->app['config']->get('app.env') != 'production') {
          foreach ($this->extCommands as $command => $single) {
            $this->{"register{$command}Command"}($command, $single);
          }
          //$commands = array_merge($commands, $this->extCommands);
          $commands = $this->extCommands;
        }

        $this->commands(array_values($commands));
    }

    public function register() {
      $this->registerCommands();
    }

    /**
     * Register the command.
     * @Feature Set general commands register
     * @return void
     * */
    public function __call($method, $parameters) {
      if (strpos($method, 'register') === 0) {
        $method = substr($method, strlen('register'));
        $this->_registerGeneralCommand($parameters);
      }
    }

    protected function _registerGeneralCommand($parameters) {
      @[$command, $alias] = $parameters;
      $command .= 'Command';
      $ns = __NAMESPACE__;
      //$ns = str_replace('Console', 'Commands', __NAMESPACE__);
      $command = "$ns\\$command";
      if (class_exists($command)) {
        $this->app->singleton($alias, function ($app) use ($command) {
          return new $command($app['files']);
        });
      }
    }
}
