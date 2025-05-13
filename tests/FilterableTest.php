<?php

namespace Filterable\Tests;

use Exception;
use Filterable\Contracts\Filter;
use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Mockery as m;

final class FilterableTest extends TestCase
{
    /**
     * A mock of a Filter interface to be applied.
     *
     * @var \Mockery\MockInterface
     */
    protected $filter;

    /**
     * The model to be used for testing, which uses the Filterable trait.
     */
    protected MockFilterable $model;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Mock the Filter interface, not the Filterable trait.
        $this->filter = m::mock(Filter::class);

        // Create an instance of a model using the Filterable trait.
        $this->model = new MockFilterable;
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        m::close();

        unset($this->model);

        parent::tearDown();
    }

    public function test_filter_applies_filters_to_model_query(): void
    {
        $options = ['option1' => 'value1'];
        $builder = m::mock(Builder::class);
        $this->filter
            ->shouldReceive('apply')
            ->once()
            ->with(m::type(Builder::class), $options)
            ->andReturn($builder);

        // Act: Use the filter method on the model instance that uses the Filterable trait.
        $result = $this->model->filter($this->filter, $options);

        // Assert: The filter method returns the expected builder instance.
        $this->assertSame($builder, $result);
    }

    public function test_filter_throws_exception_when_filter_application_fails(): void
    {
        $this->expectException(Exception::class);

        $this->filter
            ->shouldReceive('apply')
            ->once()
            ->with(m::type(Builder::class), [])
            ->andThrow(new Exception('Filter application failed'));

        // Simulate the scenario where applying the filter throws an exception.
        $this->model->filter($this->filter);
    }
}
