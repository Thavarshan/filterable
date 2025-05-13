<?php

namespace Filterable\Tests\Concerns;

use Carbon\Carbon;
use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\Fixtures\TestFilter;
use Filterable\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Mockery as m;
use ReflectionMethod;

class CachingTest extends TestCase
{
    protected $cache;

    protected $request;

    protected $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = m::mock(\Illuminate\Contracts\Cache\Repository::class);
        $this->request = new Request;

        // Create test data
        MockFilterable::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $model = new MockFilterable;
        $this->builder = $model->newQuery();

        // Enable caching globally
        TestFilter::enableCaching();
    }

    protected function tearDown(): void
    {
        TestFilter::disableCaching();
        m::close();
        parent::tearDown();
    }

    /** @test */
    public function it_caches_query_results_with_basic_caching()
    {
        $this->cache->shouldReceive('remember')
            ->once()
            ->with(
                m::type('string'),
                m::type(Carbon::class),
                m::type('Closure')
            )
            ->andReturn(collect([new MockFilterable(['name' => 'John Doe'])]));

        $filter = new TestFilter($this->request, $this->cache);

        // Apply and get results
        $filter->apply($this->builder);
        $results = $filter->get();

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(1, $results);
    }

    /** @test */
    public function it_builds_appropriate_cache_key()
    {
        $requestWithParams = Request::create('/?name=John&status=active', 'GET');

        $filter = new TestFilter($requestWithParams, $this->cache);

        // Use reflection to access protected method
        $reflectionMethod = new ReflectionMethod($filter, 'buildCacheKey');
        $reflectionMethod->setAccessible(true);

        $cacheKey = $reflectionMethod->invoke($filter);

        // Cache key should be a string
        $this->assertIsString($cacheKey);

        // Cache key should contain 'filters:' prefix
        $this->assertStringStartsWith('filters:', $cacheKey);

        // Cache key should contain an MD5 hash
        $this->assertMatchesRegularExpression('/[a-f0-9]{32}/', $cacheKey);
    }

    /** @test */
    public function it_sanitizes_array_values_in_cache_key()
    {
        $filter = new TestFilter($this->request, $this->cache);

        // Add an array value filter
        $filter->appendFilterable('tags', ['tag1', 'tag2']);

        // Use reflection to access protected method
        $reflectionMethod = new ReflectionMethod($filter, 'buildCacheKey');
        $reflectionMethod->setAccessible(true);

        $cacheKey = $reflectionMethod->invoke($filter);

        // Cache key should be a string
        $this->assertIsString($cacheKey);

        // Cache key should still be valid even with array values
        $this->assertStringStartsWith('filters:', $cacheKey);
    }

    /** @test */
    public function it_sets_and_gets_cache_expiration()
    {
        $filter = new TestFilter($this->request, $this->cache);

        // Default should be 5 minutes
        $this->assertEquals(5, $filter->getCacheExpiration());

        // Set to 60 minutes
        $filter->setCacheExpiration(60);
        $this->assertEquals(60, $filter->getCacheExpiration());
    }

    /** @test */
    public function it_clears_cache_correctly()
    {
        $this->cache->shouldReceive('forget')
            ->once()
            ->with(m::type('string'))
            ->andReturn(true);

        $filter = new TestFilter($this->request, $this->cache);
        $filter->clearCache();

        // The assertion is in the mock expectation
        $this->assertTrue(true);
    }

    /** @test */
    public function it_enables_and_disables_caching_globally()
    {
        // Should be enabled from setUp
        $this->assertTrue(TestFilter::shouldCache());

        // Disable
        TestFilter::disableCaching();
        $this->assertFalse(TestFilter::shouldCache());

        // Enable again
        TestFilter::enableCaching();
        $this->assertTrue(TestFilter::shouldCache());
    }

    /** @test */
    public function it_uses_smart_caching_for_complex_queries()
    {
        // Create a complex query that should use caching
        $builder = $this->builder
            ->where('name', 'John Doe')
            ->where('email', 'like', '%example.com')
            ->join('another_table', 'mocks.id', '=', 'another_table.mock_id');

        $this->cache->shouldReceive('tags')
            ->andReturnSelf();

        $this->cache->shouldReceive('remember')
            ->once()
            ->andReturn(collect([new MockFilterable(['name' => 'John Doe'])]));

        $filter = new TestFilter($this->request, $this->cache);
        $filter->enableFeature('caching');
        $filter->cacheTags(['mocks']);
        $filter->cacheResults(true);

        // Apply and get results
        $filter->apply($builder);
        $results = $filter->get();

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(1, $results);
    }

    /** @test */
    public function it_skips_caching_for_simple_queries()
    {
        // Create a simple query (single equality where) that should skip caching
        $builder = $this->builder->where('id', 1);

        // Set up mock to verify remember is not called
        $this->cache->shouldNotReceive('remember');

        $filter = new TestFilter($this->request, $this->cache);
        $filter->enableFeature('caching');

        // Smart caching should determine this query is too simple to cache
        $filter->apply($builder);

        // Create a spy to check if get is called directly on the builder
        $builderSpy = m::spy($builder);
        $builderSpy->shouldReceive('get')->once()->andReturn(collect());

        $filter->setBuilder($builderSpy);
        $filter->get();

        // Verification is in the spy expectations
        $builderSpy->shouldHaveReceived('get');
    }

    /** @test */
    public function it_caches_count_operations()
    {
        $this->cache->shouldReceive('remember')
            ->once()
            ->with(
                m::type('string'),
                m::type(Carbon::class),
                m::type('Closure')
            )
            ->andReturn(5);

        $filter = new TestFilter($this->request, $this->cache);
        $filter->enableFeature('caching');
        $filter->cacheCount(true);
        $filter->apply($this->builder);

        $count = $filter->count();

        $this->assertEquals(5, $count);
    }
}
