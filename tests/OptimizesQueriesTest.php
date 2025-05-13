<?php

namespace Filterable\Tests\Concerns;

use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\Fixtures\TestFilter;
use Filterable\Tests\TestCase;
use Illuminate\Http\Request;
use Mockery as m;

class OptimizesQueriesTest extends TestCase
{
    protected $cache;

    protected $request;

    protected $builder;

    protected $filter;

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

        $this->filter = new TestFilter($this->request);
        // Enable optimization
        $this->filter->enableFeature('optimization');
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    /** @test */
    public function it_selects_specific_columns()
    {
        // Create a mock builder
        $builderMock = m::mock($this->builder)->makePartial();
        $builderMock->shouldReceive('select')
            ->once()
            ->with(['id', 'name'])
            ->andReturnSelf();

        $this->filter->select(['id', 'name']);
        $this->filter->apply($builderMock);

        // The assertion is in the mock expectation
        $this->assertTrue(true);
    }

    /** @test */
    public function it_eager_loads_relationships()
    {
        // Create a mock builder
        $builderMock = m::mock($this->builder)->makePartial();
        $builderMock->shouldReceive('with')
            ->once()
            ->with(['relation1', 'relation2'])
            ->andReturnSelf();

        $this->filter->with(['relation1', 'relation2']);
        $this->filter->apply($builderMock);

        // The assertion is in the mock expectation
        $this->assertTrue(true);
    }

    /** @test */
    public function it_accepts_string_relation_for_eager_loading()
    {
        // Create a mock builder
        $builderMock = m::mock($this->builder)->makePartial();
        $builderMock->shouldReceive('with')
            ->once()
            ->with(['relation'])
            ->andReturnSelf();

        $this->filter->with('relation');
        $this->filter->apply($builderMock);

        // The assertion is in the mock expectation
        $this->assertTrue(true);
    }

    /** @test */
    public function it_sets_chunk_size_in_options()
    {
        $this->filter->chunkSize(500);

        $options = $this->filter->getOptions();
        $this->assertArrayHasKey('chunk_size', $options);
        $this->assertEquals(500, $options['chunk_size']);

        // Apply and check that use_chunking is set
        $this->filter->apply($this->builder);

        $options = $this->filter->getOptions();
        $this->assertArrayHasKey('use_chunking', $options);
        $this->assertTrue($options['use_chunking']);
    }

    /** @test */
    public function it_adds_index_hint()
    {
        // Create a mock query
        $queryMock = m::mock(\Illuminate\Database\Query\Builder::class);
        $queryMock->from = 'mocks';

        // Create a mock builder
        $builderMock = m::mock($this->builder)->makePartial();
        $builderMock->shouldReceive('getQuery')
            ->andReturn($queryMock);
        $builderMock->shouldReceive('from')
            ->once()
            ->with('mocks USE INDEX (idx_name)')
            ->andReturnSelf();

        $this->filter->useIndex('idx_name');
        $this->filter->apply($builderMock);

        // The assertion is in the mock expectation
        $this->assertTrue(true);
    }

    /** @test */
    public function it_applies_all_optimizations_together()
    {
        // Create a mock query
        $queryMock = m::mock();
        $queryMock->from = 'mocks';

        // Create a mock builder
        $builderMock = m::mock($this->builder)->makePartial();
        $builderMock->shouldReceive('getQuery')
            ->andReturn($queryMock);

        $builderMock->shouldReceive('select')
            ->once()
            ->with(['id', 'name'])
            ->andReturnSelf();

        $builderMock->shouldReceive('with')
            ->once()
            ->with(['relation1', 'relation2'])
            ->andReturnSelf();

        $builderMock->shouldReceive('from')
            ->once()
            ->with('mocks USE INDEX (idx_name)')
            ->andReturnSelf();

        // Set all optimization options
        $this->filter->select(['id', 'name']);
        $this->filter->with(['relation1', 'relation2']);
        $this->filter->chunkSize(500);
        $this->filter->useIndex('idx_name');

        // Apply optimizations
        $this->filter->apply($builderMock);

        // Check that options were set correctly
        $options = $this->filter->getOptions();
        $this->assertEquals(500, $options['chunk_size']);
        $this->assertTrue($options['use_chunking']);

        // The rest of the assertions are in the mock expectations
        $this->assertTrue(true);
    }
}
