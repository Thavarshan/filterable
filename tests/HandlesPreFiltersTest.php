<?php

namespace Filterable\Tests\Concerns;

use Closure;
use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\Fixtures\TestFilter;
use Filterable\Tests\TestCase;
use Illuminate\Http\Request;
use Mockery;
use Psr\Log\LoggerInterface;
use ReflectionProperty;

class HandlesPreFiltersTest extends TestCase
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
            'is_visible' => true,
        ]);

        MockFilterable::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'status' => 'inactive',
            'is_visible' => true,
        ]);

        MockFilterable::factory()->create([
            'name' => 'Hidden User',
            'email' => 'hidden@example.com',
            'status' => 'active',
            'is_visible' => false,
        ]);
    }

    public function test_registers_pre_filters(): void
    {
        // Create a filter
        $filter = new TestFilter($this->request);

        // Register a pre-filter
        $result = $filter->registerPreFilters(function ($query) {
            return $query->where('is_visible', true);
        });

        // Verify fluent interface returns the filter
        $this->assertSame($filter, $result);

        // Verify the pre-filter was registered (using reflection)
        $reflection = new ReflectionProperty($filter, 'preFilters');
        $reflection->setAccessible(true);
        $preFilters = $reflection->getValue($filter);

        $this->assertInstanceOf(Closure::class, $preFilters);
    }

    public function test_applies_pre_filters_before_regular_filters(): void
    {
        // Create a request with a filter
        $request = Request::create('/?status=active');

        // Create a filter with both pre-filters and regular filters
        $filter = new class($request) extends TestFilter
        {
            protected array $filters = ['status'];

            // Track method calls to verify order
            public $calls = [];

            protected function applyPreFilters(): void
            {
                $this->calls[] = 'applyPreFilters';
                parent::applyPreFilters();
            }

            protected function applyFilterables(): void
            {
                $this->calls[] = 'applyFilterables';
                parent::applyFilterables();
            }

            protected function status($value): void
            {
                $this->getBuilder()->where('status', $value);
            }
        };

        // Register a pre-filter
        $filter->registerPreFilters(function ($query) {
            return $query->where('is_visible', true);
        });

        // Apply filter
        $filter->apply($this->builder);

        // Verify order of method calls
        $this->assertEquals(['applyPreFilters', 'applyFilterables'], $filter->calls);

        // Execute query
        $results = $filter->getBuilder()->get();

        // Verify only one result matches (John Doe - active and visible)
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results->first()->name);
    }

    public function test_filters_query_with_pre_filters(): void
    {
        // First, let's check how many visible records we have in the test database
        $visibleCount = MockFilterable::where('is_visible', true)->count();

        // Create a filter
        $filter = new TestFilter($this->request);

        // Register a pre-filter that only shows visible records
        $filter->registerPreFilters(function ($query) {
            return $query->where('is_visible', true);
        });

        // Apply filter
        $filter->apply($this->builder);

        // Execute query
        $results = $filter->getBuilder()->get();

        // Verify only visible records are returned
        $this->assertCount($visibleCount, $results);
        foreach ($results as $result) {
            $this->assertTrue((bool) $result->is_visible);
        }
    }

    public function test_feature_flags_control_behavior(): void
    {
        // Create a mock logger
        $logger = Mockery::mock(LoggerInterface::class);

        // With logging disabled, logger should not be called
        $logger->shouldNotReceive('info')
            ->with('Applying pre-filters');

        // Create a filter with logging disabled
        $filter = new TestFilter($this->request, null, $logger);
        $filter->disableFeature('logging');

        // Register a pre-filter
        $filter->registerPreFilters(function ($query) {
            return $query->where('is_visible', true);
        });

        // Apply filter - this should not log anything
        $filter->apply($this->builder);

        // Now clear the mock to reset expectations
        Mockery::close();

        // Create a new mock logger
        $logger = Mockery::mock(LoggerInterface::class);

        // Now set expectations for the enabled logging scenario
        $logger->shouldReceive('info')
            ->with('Beginning filter application', Mockery::type('array'))
            ->once();

        $logger->shouldReceive('info')
            ->with('Applying pre-filters', Mockery::type('array'))
            ->once();

        $logger->shouldReceive('info')
            ->with('Filter application completed', Mockery::type('array'))
            ->once();

        // Create a new filter with logging enabled
        $filter = new TestFilter($this->request, null, $logger);
        $filter->enableFeature('logging');

        // Register a pre-filter
        $filter->registerPreFilters(function ($query) {
            return $query->where('is_visible', true);
        });

        // Apply filter again - this should log
        $filter->apply($this->builder);
    }

    public function test_does_not_apply_null_pre_filters(): void
    {
        // Create a filter with a spy to track method calls
        $filter = new class($this->request) extends TestFilter
        {
            public $preFiltersCalled = false;

            protected function applyPreFilters(): void
            {
                $this->preFiltersCalled = true;
                parent::applyPreFilters();
            }
        };

        // Apply filter without registering pre-filters
        $filter->apply($this->builder);

        // Verify applyPreFilters was called
        $this->assertTrue($filter->preFiltersCalled);

        // But no actual filtering should have happened
        $results = $filter->getBuilder()->get();
        $this->assertCount(3, $results); // All records should be returned
    }

    public function test_combines_pre_filters_with_regular_filters(): void
    {
        // Create a request with a regular filter
        $request = Request::create('/?name=Doe');

        // Create a filter that will apply both pre-filters and regular filters
        $filter = new class($request) extends TestFilter
        {
            protected array $filters = ['name'];

            protected function name($value): void
            {
                $this->getBuilder()->where('name', 'LIKE', "%{$value}%");
            }
        };

        // Register a pre-filter
        $filter->registerPreFilters(function ($query) {
            return $query->where('status', 'active');
        });

        // Apply filter
        $filter->apply($this->builder);

        // Execute query
        $results = $filter->getBuilder()->get();

        // Verify only one result matches (John Doe - has 'Doe' in name and is active)
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results->first()->name);
        $this->assertEquals('active', $results->first()->status);
    }

    public function test_uses_complex_pre_filters(): void
    {
        // Create a filter
        $filter = new TestFilter($this->request);

        // Register a complex pre-filter with multiple conditions
        $filter->registerPreFilters(function ($query) {
            return $query->where(function ($q) {
                $q->where('status', 'active')
                    ->orWhere('name', 'LIKE', '%Jane%');
            })->where('is_visible', true);
        });

        // Apply filter
        $filter->apply($this->builder);

        // Execute query
        $results = $filter->getBuilder()->get();

        // Verify results (should be John and Jane, not Hidden User)
        $this->assertCount(2, $results);
        $names = $results->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Doe', $names);
        $this->assertNotContains('Hidden User', $names);
    }

    public function test_logs_pre_filter_application_when_logging_enabled(): void
    {
        // Create a mock logger
        $logger = Mockery::mock(LoggerInterface::class);

        // Setup expectations for all expected log calls
        $logger->shouldReceive('info')
            ->with('Beginning filter application', Mockery::type('array'))
            ->once();

        $logger->shouldReceive('info')
            ->with('Applying pre-filters', Mockery::type('array'))
            ->once();

        $logger->shouldReceive('info')
            ->with('Filter application completed', Mockery::type('array'))
            ->once();

        // Create a filter with logging feature enabled
        $filter = new class($this->request, null, $logger) extends TestFilter
        {
            // Use the feature flag system instead of static method
            public function hasFeature(string $feature): bool
            {
                return $feature === 'logging';
            }
        };

        // Register a pre-filter
        $filter->registerPreFilters(function ($query) {
            return $query->where('is_visible', true);
        });

        // Apply filter
        $filter->apply($this->builder);

        // Verification is in the mock expectations
    }
}
