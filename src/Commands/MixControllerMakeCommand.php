<?php

namespace Lx\Commands;

use Package\LumenExtra\GeneratorCommand;

class MixControllerMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'make:mixcontroller';

    /**
     * @var string
     */
    protected $description = 'Make a new http controller.';

    protected $type = 'Controller';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/stubs/mixcontroller.stub';
    }

    protected function getDefaultNamespace($namespace)
    {
        return $namespace.'\Http\Controllers';
    }
}
