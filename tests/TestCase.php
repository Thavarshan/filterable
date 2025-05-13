<?php

namespace Filterable\Tests;

use Filterable\Providers\FilterableServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    use DatabaseMigrations;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase($this->app);

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Filterable\\Tests\\Fixtures\\'.class_basename($modelName).'Factory'
        );
    }

    /**
     * Set up the database for the test.
     */
    protected function setUpDatabase(Application $app): void
    {
        $schemaBuilder = $app['db']->connection('mysql')->getSchemaBuilder();

        if ($schemaBuilder->hasTable('migrations')) {
            $schemaBuilder->drop('migrations');
        }

        if ($schemaBuilder->hasTable('mocks')) {
            $schemaBuilder->drop('mocks');
        }

        $schemaBuilder->create('migrations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
        });

        $schemaBuilder->create('mocks', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->nullable();
            $table->integer('age')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });
    }

    /**
     * {@inheritdoc}
     */
    protected function getPackageProviders($app)
    {
        return [
            FilterableServiceProvider::class,
        ];
    }
}
