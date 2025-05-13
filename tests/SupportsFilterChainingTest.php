<?php

namespace Filterable\Tests\Concerns;

use Filterable\Tests\Fixtures\TestFilter;
use Filterable\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Mockery as m;
use ReflectionMethod;
use ReflectionProperty;

class SupportsFilterChainingTest extends TestCase
{
    protected $request;

    protected $builder;

    protected $filter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new Request;
        $this->builder = m::mock(Builder::class);
        $this->filter = new TestFilter($this->request);
        $this->filter->setBuilder($this->builder);
        $this->filter->enableFeature('filterChaining');
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_can_add_where_clause(): void
    {
        // Test with three arguments (column, operator, value)
        $result = $this->filter->where('column', '=', 'value');

        // Check fluent interface
        $this->assertSame($this->filter, $result);

        // Verify the custom filter was added
        $customFiltersProperty = new ReflectionProperty($this->filter, 'customFilters');
        $customFiltersProperty->setAccessible(true);
        $customFilters = $customFiltersProperty->getValue($this->filter);

        $this->assertCount(1, $customFilters);
        $this->assertInstanceOf(\Closure::class, $customFilters[0]);
    }

    public function test_can_add_where_clause_with_two_arguments(): void
    {
        // Test with two arguments (column, value) - should default to = operator
        $result = $this->filter->where('column', 'value');

        // Check fluent interface
        $this->assertSame($this->filter, $result);

        // Verify the custom filter was added
        $customFiltersProperty = new ReflectionProperty($this->filter, 'customFilters');
        $customFiltersProperty->setAccessible(true);
        $customFilters = $customFiltersProperty->getValue($this->filter);

        $this->assertCount(1, $customFilters);
        $this->assertInstanceOf(\Closure::class, $customFilters[0]);
    }

    public function test_can_add_where_in_clause(): void
    {
        // Add a whereIn filter
        $result = $this->filter->whereIn('column', [1, 2, 3]);

        // Check fluent interface
        $this->assertSame($this->filter, $result);

        // Verify the custom filter was added
        $customFiltersProperty = new ReflectionProperty($this->filter, 'customFilters');
        $customFiltersProperty->setAccessible(true);
        $customFilters = $customFiltersProperty->getValue($this->filter);

        $this->assertCount(1, $customFilters);
        $this->assertInstanceOf(\Closure::class, $customFilters[0]);
    }

    public function test_can_add_where_not_in_clause(): void
    {
        // Add a whereNotIn filter
        $result = $this->filter->whereNotIn('column', [1, 2, 3]);

        // Check fluent interface
        $this->assertSame($this->filter, $result);

        // Verify the custom filter was added
        $customFiltersProperty = new ReflectionProperty($this->filter, 'customFilters');
        $customFiltersProperty->setAccessible(true);
        $customFilters = $customFiltersProperty->getValue($this->filter);

        $this->assertCount(1, $customFilters);
        $this->assertInstanceOf(\Closure::class, $customFilters[0]);
    }

    public function test_can_add_where_between_clause(): void
    {
        // Add a whereBetween filter
        $result = $this->filter->whereBetween('column', [10, 20]);

        // Check fluent interface
        $this->assertSame($this->filter, $result);

        // Verify the custom filter was added
        $customFiltersProperty = new ReflectionProperty($this->filter, 'customFilters');
        $customFiltersProperty->setAccessible(true);
        $customFilters = $customFiltersProperty->getValue($this->filter);

        $this->assertCount(1, $customFilters);
        $this->assertInstanceOf(\Closure::class, $customFilters[0]);
    }

    public function test_can_add_order_by_clause(): void
    {
        // Add an orderBy filter with default direction (asc)
        $result = $this->filter->orderBy('column');

        // Check fluent interface
        $this->assertSame($this->filter, $result);

        // Verify the custom filter was added
        $customFiltersProperty = new ReflectionProperty($this->filter, 'customFilters');
        $customFiltersProperty->setAccessible(true);
        $customFilters = $customFiltersProperty->getValue($this->filter);

        $this->assertCount(1, $customFilters);
        $this->assertInstanceOf(\Closure::class, $customFilters[0]);
    }

    public function test_can_add_order_by_clause_with_direction(): void
    {
        // Add an orderBy filter with explicit direction
        $result = $this->filter->orderBy('column', 'desc');

        // Check fluent interface
        $this->assertSame($this->filter, $result);

        // Verify the custom filter was added
        $customFiltersProperty = new ReflectionProperty($this->filter, 'customFilters');
        $customFiltersProperty->setAccessible(true);
        $customFilters = $customFiltersProperty->getValue($this->filter);

        $this->assertCount(1, $customFilters);
        $this->assertInstanceOf(\Closure::class, $customFilters[0]);
    }

    public function test_can_chain_multiple_filters(): void
    {
        // Chain multiple filters
        $this->filter->where('column1', 'value1')
            ->whereIn('column2', [1, 2, 3])
            ->whereBetween('column3', [10, 20])
            ->orderBy('column4', 'desc');

        // Verify all custom filters were added
        $customFiltersProperty = new ReflectionProperty($this->filter, 'customFilters');
        $customFiltersProperty->setAccessible(true);
        $customFilters = $customFiltersProperty->getValue($this->filter);

        $this->assertCount(4, $customFilters);
    }

    public function test_applies_custom_filters_to_query(): void
    {
        // Set up expectations for the builder
        $this->builder->shouldReceive('where')
            ->once()
            ->with('column1', '=', 'value1')
            ->andReturnSelf();

        $this->builder->shouldReceive('whereIn')
            ->once()
            ->with('column2', [1, 2, 3])
            ->andReturnSelf();

        // Add custom filters
        $this->filter->where('column1', 'value1')
            ->whereIn('column2', [1, 2, 3]);

        // Call applyCustomFilters method
        $applyCustomFiltersMethod = new ReflectionMethod($this->filter, 'applyCustomFilters');
        $applyCustomFiltersMethod->setAccessible(true);
        $applyCustomFiltersMethod->invoke($this->filter);

        // Verify that the builder methods were called (via Mockery expectations)
        $this->assertTrue(true, 'Custom filters were applied to query');
    }

    public function test_where_applies_correct_query(): void
    {
        // Set up expectations for the builder
        $this->builder->shouldReceive('where')
            ->once()
            ->with('column', '=', 'value')
            ->andReturnSelf();

        // Add and apply the filter
        $this->filter->where('column', 'value');

        // Get the custom filter and execute it
        $customFiltersProperty = new ReflectionProperty($this->filter, 'customFilters');
        $customFiltersProperty->setAccessible(true);
        $customFilters = $customFiltersProperty->getValue($this->filter);

        $customFilters[0]($this->builder);

        // Verify that the builder method was called correctly (via Mockery expectations)
        $this->assertTrue(true, 'Where clause was applied correctly');
    }

    public function test_where_in_applies_correct_query(): void
    {
        // Set up expectations for the builder
        $this->builder->shouldReceive('whereIn')
            ->once()
            ->with('column', [1, 2, 3])
            ->andReturnSelf();

        // Add and apply the filter
        $this->filter->whereIn('column', [1, 2, 3]);

        // Get the custom filter and execute it
        $customFiltersProperty = new ReflectionProperty($this->filter, 'customFilters');
        $customFiltersProperty->setAccessible(true);
        $customFilters = $customFiltersProperty->getValue($this->filter);

        $customFilters[0]($this->builder);

        // Verify that the builder method was called correctly (via Mockery expectations)
        $this->assertTrue(true, 'WhereIn clause was applied correctly');
    }

    public function test_where_not_in_applies_correct_query(): void
    {
        // Set up expectations for the builder
        $this->builder->shouldReceive('whereNotIn')
            ->once()
            ->with('column', [1, 2, 3])
            ->andReturnSelf();

        // Add and apply the filter
        $this->filter->whereNotIn('column', [1, 2, 3]);

        // Get the custom filter and execute it
        $customFiltersProperty = new ReflectionProperty($this->filter, 'customFilters');
        $customFiltersProperty->setAccessible(true);
        $customFilters = $customFiltersProperty->getValue($this->filter);

        $customFilters[0]($this->builder);

        // Verify that the builder method was called correctly (via Mockery expectations)
        $this->assertTrue(true, 'WhereNotIn clause was applied correctly');
    }

    public function test_where_between_applies_correct_query(): void
    {
        // Set up expectations for the builder
        $this->builder->shouldReceive('whereBetween')
            ->once()
            ->with('column', [10, 20])
            ->andReturnSelf();

        // Add and apply the filter
        $this->filter->whereBetween('column', [10, 20]);

        // Get the custom filter and execute it
        $customFiltersProperty = new ReflectionProperty($this->filter, 'customFilters');
        $customFiltersProperty->setAccessible(true);
        $customFilters = $customFiltersProperty->getValue($this->filter);

        $customFilters[0]($this->builder);

        // Verify that the builder method was called correctly (via Mockery expectations)
        $this->assertTrue(true, 'WhereBetween clause was applied correctly');
    }

    public function test_order_by_applies_correct_query(): void
    {
        // Set up expectations for the builder
        $this->builder->shouldReceive('orderBy')
            ->once()
            ->with('column', 'desc')
            ->andReturnSelf();

        // Add and apply the filter
        $this->filter->orderBy('column', 'desc');

        // Get the custom filter and execute it
        $customFiltersProperty = new ReflectionProperty($this->filter, 'customFilters');
        $customFiltersProperty->setAccessible(true);
        $customFilters = $customFiltersProperty->getValue($this->filter);

        $customFilters[0]($this->builder);

        // Verify that the builder method was called correctly (via Mockery expectations)
        $this->assertTrue(true, 'OrderBy clause was applied correctly');
    }

    public function test_custom_filters_applied_during_filter_application(): void
    {
        // Create a new filter with a mock builder
        $builder = m::mock(Builder::class);
        $filter = new TestFilter($this->request);

        // Add some custom filters
        $filter->where('column1', 'value1')
            ->whereIn('column2', [1, 2, 3]);

        // Enable filter chaining
        $filter->enableFeature('filterChaining');

        // Set up expectations for the builder
        $builder->shouldReceive('where')
            ->once()
            ->with('column1', '=', 'value1')
            ->andReturnSelf();

        $builder->shouldReceive('whereIn')
            ->once()
            ->with('column2', [1, 2, 3])
            ->andReturnSelf();

        // Other builder expectations for a complete filter application
        $builder->shouldReceive('get')
            ->andReturn(collect());

        // Apply the filter
        $filter->apply($builder);

        // Verify that the builder methods were called (via Mockery expectations)
        $this->assertTrue(true, 'Custom filters were applied during filter application');
    }

    public function test_custom_filters_not_applied_when_feature_disabled(): void
    {
        // Create a new filter with a mock builder
        $builder = m::mock(Builder::class);
        $filter = new TestFilter($this->request);

        // Add some custom filters
        $filter->where('column1', 'value1')
            ->whereIn('column2', [1, 2, 3]);

        // Disable filter chaining
        $filter->disableFeature('filterChaining');

        // The filter methods should NOT be called
        $builder->shouldNotReceive('where');
        $builder->shouldNotReceive('whereIn');

        // Other builder expectations for a complete filter application
        $builder->shouldReceive('get')
            ->andReturn(collect());

        // Apply the filter
        $filter->apply($builder);

        // Verify that the builder methods were not called (via Mockery expectations)
        $this->assertTrue(true, 'Custom filters were not applied when feature was disabled');
    }
}
