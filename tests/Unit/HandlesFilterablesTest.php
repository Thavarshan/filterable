<?php

namespace Filterable\Tests\Unit;

use BadMethodCallException;
use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\Fixtures\TestFilter;
use Filterable\Tests\TestCase;
use Illuminate\Http\Request;

class HandlesFilterablesTest extends TestCase
{
    protected $request;

    protected $model;

    protected $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new Request;
        $this->model = new MockFilterable;
        $this->builder = $this->model->newQuery();

        // Create test data
        MockFilterable::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
        ]);

        MockFilterable::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'status' => 'inactive',
        ]);
    }

    public function test_applies_filters_dynamically_based_on_request(): void
    {
        // Create a request with filters
        $request = Request::create('/?name=John&status=active');

        // Create a filter with specific implementation
        $filter = new class($request) extends TestFilter
        {
            protected array $filters = ['name', 'status'];

            protected function name($value): void
            {
                $this->getBuilder()->where('name', 'LIKE', "%{$value}%");
            }

            protected function status($value): void
            {
                $this->getBuilder()->where('status', $value);
            }
        };

        // Apply filter
        $filter->apply($this->builder);

        // Execute query
        $results = $filter->getBuilder()->get();

        // Verify only one result matches (John Doe with active status)
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results->first()->name);
        $this->assertEquals('active', $results->first()->status);
    }

    public function test_applies_filters_with_custom_method_mapping(): void
    {
        // Create a request with custom filter names
        $request = Request::create('/?user_name=Jane&user_status=inactive');

        // Create a filter with method mapping
        $filter = new class($request) extends TestFilter
        {
            protected array $filters = [];

            protected array $filterMethodMap = [
                'user_name' => 'filterByName',
                'user_status' => 'filterByStatus',
            ];

            protected function filterByName($value): void
            {
                $this->getBuilder()->where('name', 'LIKE', "%{$value}%");
            }

            protected function filterByStatus($value): void
            {
                $this->getBuilder()->where('status', $value);
            }
        };

        // Apply filter
        $filter->apply($this->builder);

        // Execute query
        $results = $filter->getBuilder()->get();

        // Verify only one result matches (Jane Doe with inactive status)
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Doe', $results->first()->name);
        $this->assertEquals('inactive', $results->first()->status);
    }

    public function test_skips_empty_filters(): void
    {
        // Create a request with an empty filter
        $request = Request::create('/?name=&status=active');

        // Create a filter that would apply both filters if present
        $filter = new class($request) extends TestFilter
        {
            protected array $filters = ['name', 'status'];

            // Track which methods are called
            public $called = [];

            protected function name($value): void
            {
                $this->called[] = 'name';
                $this->getBuilder()->where('name', 'LIKE', "%{$value}%");
            }

            protected function status($value): void
            {
                $this->called[] = 'status';
                $this->getBuilder()->where('status', $value);
            }
        };

        // Apply filter
        $filter->apply($this->builder);

        // Verify only the status filter was applied
        $this->assertContains('status', $filter->called);
        $this->assertNotContains('name', $filter->called);

        // Verify the query returns only active records
        $results = $filter->getBuilder()->get();
        $this->assertCount(1, $results);
        $this->assertEquals('active', $results->first()->status);
    }

    public function test_throws_exception_for_undefined_filter_method(): void
    {
        // Create a request with a filter that doesn't have a method
        $request = Request::create('/?undefined_filter=value');

        // Create a filter that defines the filter but not the method
        $filter = new class($request) extends TestFilter
        {
            protected array $filters = ['undefined_filter'];

            // Expose the protected method for testing
            public function callApplyFilterable(string $filter, mixed $value): void
            {
                $this->applyFilterable($filter, $value);
            }
        };

        // Expect exception when applying the filter directly
        $this->expectException(BadMethodCallException::class);

        // Call the method that should throw the exception
        $filter->callApplyFilterable('undefined_filter', 'value');
    }

    public function test_appends_filterables_programmatically(): void
    {
        // Create a request with no filters
        $request = new Request;

        // Create a filter
        $filter = new class($request) extends TestFilter
        {
            protected array $filters = ['name', 'status'];

            protected function name($value): void
            {
                $this->getBuilder()->where('name', 'LIKE', "%{$value}%");
            }

            protected function status($value): void
            {
                $this->getBuilder()->where('status', $value);
            }
        };

        // Append filters programmatically
        $filter->appendFilterable('name', 'Jane');
        $filter->appendFilterable('status', 'inactive');

        // Apply filter
        $filter->apply($this->builder);

        // Execute query
        $results = $filter->getBuilder()->get();

        // Verify only one result matches (Jane Doe with inactive status)
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Doe', $results->first()->name);
        $this->assertEquals('inactive', $results->first()->status);
    }

    public function test_gets_current_filters(): void
    {
        // Create a request with filters
        $request = Request::create('/?name=John&status=active');

        // Create a filter
        $filter = new class($request) extends TestFilter
        {
            protected array $filters = ['name', 'status', 'email'];

            protected function name($value): void
            {
                $this->getBuilder()->where('name', 'LIKE', "%{$value}%");
            }

            protected function status($value): void
            {
                $this->getBuilder()->where('status', $value);
            }

            protected function email($value): void
            {
                $this->getBuilder()->where('email', $value);
            }
        };

        // Apply filter
        $filter->apply($this->builder);

        // Verify current filters
        $currentFilters = $filter->getCurrentFilters();
        $this->assertCount(2, $currentFilters);
        $this->assertContains('name', $currentFilters);
        $this->assertContains('status', $currentFilters);
        $this->assertNotContains('email', $currentFilters);
    }

    public function test_gets_registered_filters(): void
    {
        // Create a filter with defined filters
        $filter = new class(new Request) extends TestFilter
        {
            protected array $filters = ['name', 'status', 'email'];
        };

        // Get registered filters
        $filters = $filter->getFilters();

        // Verify filters
        $this->assertCount(3, $filters);
        $this->assertContains('name', $filters);
        $this->assertContains('status', $filters);
        $this->assertContains('email', $filters);
    }

    public function test_collects_filters_from_both_filters_and_method_map(): void
    {
        // Create a request with filters from both arrays
        $request = Request::create('/?name=John&custom_filter=value');

        // Create a filter with both regular filters and method mapped filters
        $filter = new class($request) extends TestFilter
        {
            protected array $filters = ['name'];

            protected array $filterMethodMap = [
                'custom_filter' => 'customFilterMethod',
            ];

            // Track applied filters
            public $applied = [];

            protected function name($value): void
            {
                $this->applied[] = 'name';
                $this->getBuilder()->where('name', 'LIKE', "%{$value}%");
            }

            protected function customFilterMethod($value): void
            {
                $this->applied[] = 'custom_filter';
                // No query modification needed for test
            }
        };

        // Apply filter
        $filter->apply($this->builder);

        // Verify both filters were applied
        $this->assertContains('name', $filter->applied);
        $this->assertContains('custom_filter', $filter->applied);

        // Verify filterables contains both
        $filterables = $filter->getFilterables();
        $this->assertArrayHasKey('name', $filterables);
        $this->assertArrayHasKey('custom_filter', $filterables);
    }

    public function test_creates_collection_filter_from_filterables(): void
    {
        // Create a request with filters
        $request = Request::create('/?name=John&status=active');

        // Create a filter
        $filter = new class($request) extends TestFilter
        {
            protected array $filters = ['name', 'status'];
        };

        // Get collection filter
        $collectionFilter = $filter->asCollectionFilter();

        // Verify it's a closure
        $this->assertInstanceOf(\Closure::class, $collectionFilter);

        // Create a test collection
        $collection = collect([
            ['name' => 'John', 'status' => 'active'],
            ['name' => 'Jane', 'status' => 'inactive'],
        ]);

        // Apply the collection filter
        $filteredCollection = $collectionFilter($collection);

        // Verify it returns the filterables
        $this->assertEquals(collect(['name' => 'John', 'status' => 'active']), $filteredCollection);
    }
}
