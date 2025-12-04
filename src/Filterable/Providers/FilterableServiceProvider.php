<?php

namespace Filterable\Providers;

use Filterable\Console\MakeFilterCommand;
use Illuminate\Http\Request;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilterableServiceProvider extends PackageServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function configurePackage(Package $package): void
    {
        $package->name('filterable')
            ->hasConfigFile('filterable')
            ->hasCommand(MakeFilterCommand::class);
    }

    /**
     * {@inheritdoc}
     */
    public function packageRegistered(): void
    {
        $this->registerFilterBindings();
    }

    /**
     * Register contextual bindings for Filter classes.
     *
     * This ensures that when a Filter subclass is resolved from the DI container,
     * it receives the current HTTP request instance rather than an empty Request.
     */
    protected function registerFilterBindings(): void
    {
        // Bind Request class to resolve to the current request from the container.
        // This ensures Filter subclasses get the active HTTP request instead of an empty one.
        // Using bindIf() ensures we don't override any existing Request bindings.
        // Tests can still override this by calling $app->instance(Request::class, $request).
        $this->app->bindIf(Request::class, function ($app) {
            return $app['request'];
        });
    }

    /**
     * {@inheritdoc}
     */
    protected function getPackageBaseDir(): string
    {
        return dirname(__DIR__, 2);
    }
}
