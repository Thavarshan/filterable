<?php

namespace Filterable\Tests\Integration;

use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\Fixtures\TestFilter;
use Filterable\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class FilterLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Seed a few records
        MockFilterable::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active']);
        MockFilterable::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'inactive']);
        MockFilterable::factory()->create(['name' => 'Charlie', 'email' => 'charlie@example.com', 'status' => 'active']);

        // Force cache to array store for deterministic behaviour
        config()->set('cache.default', 'array');
        Cache::store('array')->clear();
    }

    public function test_applies_filter_with_features_and_respects_configuration_defaults(): void
    {
        config()->set('filterable.defaults.features', [
            'caching' => true,
            'memoryManagement' => true,
            'rateLimit' => true,
        ]);

        config()->set('filterable.defaults.options', [
            'chunk_size' => 2,
        ]);
        config()->set('filterable.defaults.cache.ttl', 30);

        $request = new \Illuminate\Http\Request(['status' => 'active']);

        $filter = new class($request, Cache::store('array')) extends TestFilter
        {
            protected array $filters = ['status'];

            protected function status(string $value): void
            {
                $this->getBuilder()->where('status', $value);
            }
        };

        $filter->enableFeatures(['logging', 'performance']);
        $filter->enableFeature('caching');
        $filter->forUser($this->createMockUser(1));
        $filter->appendFilterable('status', 'active');
        $filter->cacheResults();

        $this->assertEquals(3, MockFilterable::count());
        $this->assertEqualsCanonicalizing(['active', 'inactive', 'active'], MockFilterable::orderBy('id')->pluck('status')->all());
        $this->assertCount(2, MockFilterable::query()->where('status', 'active')->get());
        $this->assertSame(['status' => 'active'], $filter->getFilterables());

        $builder = MockFilterable::query();
        $filteredBuilder = $builder->filter($filter);

        $this->assertSame($filteredBuilder, $filter->getBuilder());
        $this->assertStringContainsString('"status" = ?', $filteredBuilder->toSql());
        $this->assertContains('active', $filteredBuilder->getBindings());
        $this->assertCount(1, (clone $filteredBuilder)->get());

        $results = $filter->get();

        $this->assertCount(1, $results);
        $this->assertEquals(['Alice'], $results->pluck('name')->all());
        $this->assertTrue($filter->hasFeature('caching'));
        $this->assertTrue($filter->hasFeature('memoryManagement'));
        $this->assertEquals(30, $filter->getCacheExpiration());

        $cacheKey = $filter->buildCacheKey();
        $this->assertTrue(Cache::has($cacheKey));

        $cachedResults = Cache::get($cacheKey);
        $this->assertCount(1, $cachedResults);

        $debug = $filter->getDebugInfo();
        $this->assertSame('applied', $debug['state']);
        $this->assertArrayHasKey('performance', $debug['features_enabled']);
        $this->assertArrayHasKey('filters_applied', $debug);
        $this->assertArrayHasKey('sql', $debug);
    }

    protected function createMockUser(int $id)
    {
        $user = new class($id) implements \Illuminate\Contracts\Auth\Authenticatable {
            public function __construct(public int $identifier)
            {
            }

            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return $this->identifier;
            }

            public function getAuthPassword()
            {
                return '';
            }

            public function getAuthPasswordName()
            {
                return 'password';
            }

            public function getRememberToken()
            {
                return null;
            }

            public function setRememberToken($value): void
            {
            }

            public function getRememberTokenName()
            {
                return 'remember_token';
            }
        };

        return $user;
    }
}
