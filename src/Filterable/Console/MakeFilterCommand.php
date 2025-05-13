<?php

namespace Filterable\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:filter')]
class MakeFilterCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:filter';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new filter class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Filter';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        if ($this->option('basic')) {
            return $this->resolveStubPath('/stubs/filter.basic.stub');
        }

        if ($this->option('model')) {
            return $this->resolveStubPath('/stubs/filter.model.stub');
        }

        return $this->resolveStubPath('/stubs/filter.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     */
    protected function resolveStubPath($stub): string
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.$stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Filters';
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        if ($model = $this->option('model')) {
            $stub = $this->replaceModel($stub, $model);
        }

        return $stub;
    }

    /**
     * Replace the model for the given stub.
     */
    protected function replaceModel(string $stub, string $model): string
    {
        $modelClass = $this->parseModel($model);

        $replace = [
            '{{ namespacedModel }}' => $modelClass,
            '{{namespacedModel}}' => $modelClass,
            '{{ model }}' => class_basename($modelClass),
            '{{model}}' => class_basename($modelClass),
            '{{ modelVariable }}' => Str::camel(class_basename($modelClass)),
            '{{modelVariable}}' => Str::camel(class_basename($modelClass)),
        ];

        return str_replace(
            array_keys($replace),
            array_values($replace),
            $stub
        );
    }

    /**
     * Get the fully-qualified model class name.
     */
    protected function parseModel(string $model): string
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
            throw new InvalidArgumentException('Model name contains invalid characters.');
        }

        return $this->qualifyModel($model);
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['basic', 'b', InputOption::VALUE_NONE, 'Create a basic filter class'],
            ['model', 'm', InputOption::VALUE_OPTIONAL, 'Generate a filter for the given model'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the filter already exists'],
        ];
    }
}
