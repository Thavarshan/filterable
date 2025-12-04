<?php

namespace Filterable\Tests\Integration;

use Filterable\Filter;
use Filterable\Tests\Fixtures\MockFilter;
use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\TestCase;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

/**
 * Tests for DI container resolution of Filter classes.
 *
 * These tests ensure that Filter subclasses receive the current HTTP request
 * when resolved from Laravel's DI container, rather than an empty Request instance.
 *
 * @see https://github.com/Thavarshan/filterable/issues/30
 */
class FilterDependencyInjectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        MockFilterable::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
        ]);

        MockFilterable::factory()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'status' => 'inactive',
        ]);

        MockFilterable::factory()->create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'status' => 'active',
        ]);
    }

    /**
     * Regression test for GitHub issue #30.
     *
     * This test verifies that when a Filter is resolved from the DI container
     * (as happens in controller method injection), it receives the current HTTP
     * request with all its parameters, not an empty Request instance.
     *
     * @see https://github.com/Thavarshan/filterable/issues/30
     */
    public function test_filter_resolved_from_container_receives_current_request(): void
    {
        // Simulate a request with filter parameters
        $this->app['request']->merge([
            'name' => 'John',
        ]);

        // Resolve the filter from the DI container (as Laravel would in a controller)
        $filter = $this->app->make(MockFilter::class);

        // The filter should have access to the request parameters
        $filterables = $filter->getFilterables();

        $this->assertArrayHasKey('name', $filterables);
        $this->assertEquals('John', $filterables['name']);
    }

    public function test_filter_resolved_from_container_applies_filters_correctly(): void
    {
        // Replace the request to ensure a clean state with only our filter parameters
        // Using 'John Doe' to match exactly one record (not 'John' which matches 'Bob Johnson' too)
        $request = Request::create('/posts', 'GET', [
            'name' => 'John Doe',
        ]);

        $this->app->instance('request', $request);
        $this->app->instance(Request::class, $request);

        // Resolve the filter from the DI container
        $filter = $this->app->make(MockFilter::class);

        // Apply the filter to a query
        $results = MockFilterable::query()->filter($filter)->get();

        // Should only return John Doe
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results->first()->name);
    }

    public function test_filter_resolved_with_multiple_parameters(): void
    {
        // Simulate a request with multiple filter parameters
        $this->app['request']->merge([
            'name' => 'J',
            'email' => 'example.com',
        ]);

        // Resolve the filter from the DI container
        $filter = $this->app->make(MockFilter::class);

        // The filter should have access to all request parameters
        $filterables = $filter->getFilterables();

        $this->assertArrayHasKey('name', $filterables);
        $this->assertArrayHasKey('email', $filterables);
        $this->assertEquals('J', $filterables['name']);
        $this->assertEquals('example.com', $filterables['email']);
    }

    public function test_filter_resolved_via_controller_style_injection(): void
    {
        // Simulate a request with filter parameters
        $this->app['request']->merge([
            'name' => 'Jane',
        ]);

        // Simulate controller-style method injection
        $controller = new class
        {
            public function index(MockFilter $filter): array
            {
                return $filter->getFilterables();
            }
        };

        // Resolve the controller method dependencies (simulating Laravel's behavior)
        $filter = $this->app->make(MockFilter::class);
        $filterables = $controller->index($filter);

        $this->assertArrayHasKey('name', $filterables);
        $this->assertEquals('Jane', $filterables['name']);
    }

    public function test_filter_works_correctly_when_request_is_empty(): void
    {
        // Ensure request has no filter parameters
        $this->app['request']->replace([]);

        // Resolve the filter from the DI container
        $filter = $this->app->make(MockFilter::class);

        // The filterables should be empty
        $filterables = $filter->getFilterables();

        $this->assertEmpty($filterables);

        // Applying an empty filter should return all records
        $results = MockFilterable::query()->filter($filter)->get();

        $this->assertCount(3, $results);
    }

    public function test_filter_resolved_from_container_with_query_string_parameters(): void
    {
        // Create a request with query parameters (like ?name=John&email=test)
        $request = Request::create('/posts', 'GET', [
            'name' => 'Bob',
            'email' => 'bob@',
        ]);

        // Replace the current request in the container
        $this->app->instance('request', $request);
        $this->app->instance(Request::class, $request);

        // Resolve the filter from the DI container
        $filter = $this->app->make(MockFilter::class);

        // The filter should have access to the query parameters
        $filterables = $filter->getFilterables();

        $this->assertArrayHasKey('name', $filterables);
        $this->assertArrayHasKey('email', $filterables);
        $this->assertEquals('Bob', $filterables['name']);
        $this->assertEquals('bob@', $filterables['email']);

        // Apply the filter
        $results = MockFilterable::query()->filter($filter)->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results->first()->name);
    }

    public function test_filter_injection_works_with_other_dependencies(): void
    {
        // Simulate a request with filter parameters
        $request = Request::create('/posts', 'GET', [
            'name' => 'John',
        ]);

        $this->app->instance('request', $request);
        $this->app->instance(Request::class, $request);

        // Bind cache and logger so they get injected
        $cache = $this->app->make(Cache::class);
        $logger = $this->app->make(LoggerInterface::class);

        $this->app->when(FilterWithDependencies::class)
            ->needs(Cache::class)
            ->give(fn () => $cache);

        $this->app->when(FilterWithDependencies::class)
            ->needs(LoggerInterface::class)
            ->give(fn () => $logger);

        // Create a filter with additional dependencies injected
        $filter = $this->app->make(FilterWithDependencies::class);

        // The filter should have access to the request parameters
        $filterables = $filter->getFilterables();

        $this->assertArrayHasKey('name', $filterables);
        $this->assertEquals('John', $filterables['name']);

        // Cache and logger should be injected when explicitly bound
        $this->assertTrue($filter->hasCacheHandler());
        $this->assertTrue($filter->hasLogger());
    }

    public function test_multiple_filter_resolutions_use_current_request(): void
    {
        // First request
        $this->app['request']->merge(['name' => 'John']);
        $filter1 = $this->app->make(MockFilter::class);

        // Modify request (simulating a different request)
        $newRequest = Request::create('/posts', 'GET', ['name' => 'Jane']);
        $this->app->instance('request', $newRequest);
        $this->app->instance(Request::class, $newRequest);

        // Second filter resolution should get the new request
        $filter2 = $this->app->make(MockFilter::class);

        // Filter 1 was already constructed with 'John'
        $filterables1 = $filter1->getFilterables();
        $this->assertEquals('John', $filterables1['name']);

        // Filter 2 should be constructed with 'Jane'
        $filterables2 = $filter2->getFilterables();
        $this->assertEquals('Jane', $filterables2['name']);
    }

    public function test_filter_resolved_from_container_in_post_request(): void
    {
        // Create a POST request with form data
        $request = Request::create('/posts', 'POST', [
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        // Replace the current request in the container
        $this->app->instance('request', $request);
        $this->app->instance(Request::class, $request);

        // Resolve the filter from the DI container
        $filter = $this->app->make(MockFilter::class);

        // The filter should have access to the POST parameters
        $filterables = $filter->getFilterables();

        $this->assertArrayHasKey('name', $filterables);
        $this->assertArrayHasKey('email', $filterables);
    }

    public function test_filter_resolved_with_json_request_body(): void
    {
        // Create a JSON request
        $request = Request::create(
            '/posts',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'John', 'email' => 'john@example.com'])
        );

        // Replace the current request in the container
        $this->app->instance('request', $request);
        $this->app->instance(Request::class, $request);

        // Resolve the filter from the DI container
        $filter = $this->app->make(MockFilter::class);

        // The filter should have access to the JSON parameters
        $filterables = $filter->getFilterables();

        $this->assertArrayHasKey('name', $filterables);
        $this->assertEquals('John', $filterables['name']);
    }

    /**
     * Regression test for GitHub issue #30.
     *
     * This test verifies that DI-resolved filters behave identically to manually
     * instantiated filters. Before the fix, DI-resolved filters would receive an
     * empty Request, causing filter methods not to be called.
     *
     * @see https://github.com/Thavarshan/filterable/issues/30
     */
    public function test_di_resolved_filter_behaves_same_as_manually_instantiated(): void
    {
        // Set up a request with filter parameters
        $request = Request::create('/posts', 'GET', [
            'name' => 'Jane',
        ]);

        $this->app->instance('request', $request);
        $this->app->instance(Request::class, $request);

        // Method 1: Manual instantiation (always worked)
        $manualFilter = new MockFilter(request());
        $manualResults = MockFilterable::query()->filter($manualFilter)->get();

        // Method 2: DI container resolution (was broken before fix)
        $diFilter = $this->app->make(MockFilter::class);
        $diResults = MockFilterable::query()->filter($diFilter)->get();

        // Both methods should produce identical results
        $this->assertEquals(
            $manualResults->pluck('id')->toArray(),
            $diResults->pluck('id')->toArray(),
            'DI-resolved filter should produce same results as manually instantiated filter'
        );

        // Both should find only Jane Smith
        $this->assertCount(1, $manualResults);
        $this->assertCount(1, $diResults);
        $this->assertEquals('Jane Smith', $diResults->first()->name);
    }

    /**
     * Test that demonstrates the fix for GitHub issue #30.
     *
     * Without the service provider fix, this test would fail because the Filter
     * would receive an empty Request when resolved from the DI container,
     * resulting in no filters being applied and all 3 records being returned.
     *
     * @see https://github.com/Thavarshan/filterable/issues/30
     */
    public function test_filter_receives_request_parameters_when_injected_into_controller(): void
    {
        // Simulate a real HTTP request to /posts?status=active
        $request = Request::create('/posts', 'GET', [
            'status' => 'active',
        ]);

        $this->app->instance('request', $request);
        $this->app->instance(Request::class, $request);

        // Simulate what happens in a controller: public function index(StatusFilter $filter)
        $filter = $this->app->make(StatusFilter::class);

        // Verify the filter has the request parameters
        $filterables = $filter->getFilterables();
        $this->assertArrayHasKey('status', $filterables);
        $this->assertEquals('active', $filterables['status']);

        // Apply the filter - should only return active records
        $results = MockFilterable::query()->filter($filter)->get();

        // Should return 2 active records (John Doe and Bob Johnson), not all 3
        $this->assertCount(2, $results);
        $this->assertTrue($results->every(fn ($r) => $r->status === 'active'));
    }
}

/**
 * A test filter that filters by status.
 */
class StatusFilter extends Filter
{
    protected array $filters = ['status'];

    protected function status(string $value): void
    {
        $this->getBuilder()->where('status', $value);
    }
}

/**
 * A test filter that accepts cache and logger as constructor dependencies.
 */
class FilterWithDependencies extends Filter
{
    protected array $filters = ['name', 'email'];

    public function __construct(
        Request $request,
        ?Cache $cache = null,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($request, $cache, $logger);
    }

    public function hasCacheHandler(): bool
    {
        return $this->cache !== null;
    }

    public function hasLogger(): bool
    {
        return $this->logger !== null;
    }

    protected function name(string $value): void
    {
        $this->getBuilder()->where('name', 'LIKE', "%{$value}%");
    }

    protected function email(string $value): void
    {
        $this->getBuilder()->where('email', 'LIKE', "%{$value}%");
    }
}
