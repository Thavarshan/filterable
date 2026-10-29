<?php

namespace Filterable\Tests;

use Filterable\Providers\FilterableServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    use DatabaseMigrations;

    /**
     * Runtime contract assertions intentionally verify the declared type.
     *
     * @template T of object
     *
     * @param  class-string<T>  $class
     */
    protected function assertRuntimeInstanceOf(string $class, mixed $value): void
    {
        $this->assertInstanceOf($class, $value);
    }

    /**
     * {@inheritdoc}
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        if ($driver = getenv('FILTERABLE_TEST_DB')) {
            $app['config']->set('database.connections.testing', [
                'driver' => $driver,
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: ($driver === 'mysql' ? 3306 : 5432),
                'database' => getenv('DB_DATABASE') ?: 'test_filterable',
                'username' => getenv('DB_USERNAME'),
                'password' => getenv('DB_PASSWORD'),
                'charset' => $driver === 'mysql' ? 'utf8mb4' : 'utf8',
                'prefix' => '',
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase(app());

        Factory::guessFactoryNamesUsing(function (string $modelName) {
            $factory = 'Filterable\\Tests\\Fixtures\\'.class_basename($modelName).'Factory';
            if (! is_subclass_of($factory, Factory::class)) {
                throw new \InvalidArgumentException('Unknown test model factory.');
            }

            return $factory;
        });

        config()->set('database.default', 'testing');
    }

    /**
     * Set up the database for the test.
     */
    protected function setUpDatabase(Application $app): void
    {
        $schemaBuilder = $app->make(DatabaseManager::class)->connection()->getSchemaBuilder();

        $schemaBuilder->dropIfExists('migrations');
        $schemaBuilder->dropIfExists('mocks');

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
