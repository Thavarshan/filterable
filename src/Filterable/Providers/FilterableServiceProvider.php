<?php

namespace Filterable\Providers;

use Filterable\Console\MakeFilterCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilterableServiceProvider extends PackageServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function configurePackage(Package $package): void
    {
        $package->name('jerome/filterable')
            ->hasConfigFile('filterable')
            ->hasCommand(MakeFilterCommand::class);
    }
}
