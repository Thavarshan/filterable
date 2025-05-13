<?php

namespace Filterable\Tests\Concerns;

use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\Fixtures\TestFilter;
use Filterable\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Mockery as m;

class ManagesMemoryTest extends TestCase
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
        for ($i = 0; $i < 10; $i++) {
            MockFilterable::factory()->create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
            ]);
        }

        $model = new MockFilterable;
        $this->builder = $model->newQuery();

        $this->filter = new TestFilter($this->request);
        // Enable memory management
        $this->filter->enableFeature('memoryManagement');
        $this->filter->apply($this->builder);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    /** @test */
    public function it_executes_query_with_memory_management()
    {
        // Create a mock builder
        $builderMock = m::mock($this->builder)->makePartial();
        $builderMock->shouldReceive('chunk')
            ->once()
            ->with(500, m::type('Closure'))
            ->andReturnUsing(function ($size, $callback) {
                // Simulate chunking by calling callback with results
                $results = MockFilterable::all()->take(5);
                $callback($results);

                return true;
            });

        // Create a new filter with our mock builder
        $filter = new TestFilter($this->request);
        $filter->enableFeature('memoryManagement');
        $filter->setOptions(['chunk_size' => 500]);
        $filter->apply($builderMock);

        // Get results using memory management
        $results = $filter->get();

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertGreaterThan(0, $results->count());
    }

    /** @test */
    public function it_processes_results_with_lazy_collection()
    {
        // Create a mock builder
        $builderMock = m::mock($this->builder)->makePartial();
        $builderMock->shouldReceive('lazy')
            ->once()
            ->with(500)
            ->andReturn(MockFilterable::all()->lazy(500));

        $filter = new TestFilter($this->request);
        $filter->apply($builderMock);

        // Get lazy collection
        $lazyCollection = $filter->lazy(500);

        $this->assertInstanceOf(\Illuminate\Support\LazyCollection::class, $lazyCollection);

        // Count results
        $count = 0;
        foreach ($lazyCollection as $item) {
            $count++;
        }

        $this->assertEquals(10, $count);
    }

    /** @test */
    public function it_processes_results_with_lazy_each()
    {
        // Create a mock builder
        $builderMock = m::mock($this->builder)->makePartial();
        $builderMock->shouldReceive('lazy')
            ->once()
            ->andReturn(MockFilterable::all()->lazy());

        $filter = new TestFilter($this->request);
        $filter->apply($builderMock);

        // Count items processed
        $count = 0;
        $filter->lazyEach(function ($item) use (&$count) {
            $count++;
        });

        $this->assertEquals(10, $count);
    }

    /** @test */
    public function it_maps_results_efficiently()
    {
        // Create a filter
        $filter = new TestFilter($this->request);

        // Need to mock the lazyEach method to avoid actual DB calls
        $filterMock = m::mock($filter)->makePartial();
        $filterMock->shouldReceive('lazyEach')
            ->once()
            ->with(m::type('Closure'), 500)
            ->andReturnUsing(function ($callback, $chunkSize) {
                // Simulate processing by calling callback with items
                foreach (MockFilterable::all() as $item) {
                    $callback($item);
                }
            });

        // Map results
        $names = $filterMock->map(function ($item) {
            return $item->name;
        }, 500);

        $this->assertIsArray($names);
        $this->assertCount(10, $names);
        $this->assertContains('User 0', $names);
    }

    /** @test */
    public function it_filters_results_efficiently()
    {
        // Create a filter
        $filter = new TestFilter($this->request);

        // Need to mock the lazyEach method to avoid actual DB calls
        $filterMock = m::mock($filter)->makePartial();
        $filterMock->shouldReceive('lazyEach')
            ->once()
            ->with(m::type('Closure'), 500)
            ->andReturnUsing(function ($callback, $chunkSize) {
                // Simulate processing by calling callback with items
                foreach (MockFilterable::all() as $item) {
                    $callback($item);
                }
            });

        // Filter results to get even numbered users
        $filtered = $filterMock->filter(function ($item) {
            return strpos($item->name, 'User') === 0 && intval(substr($item->name, 5)) % 2 === 0;
        }, 500);

        $this->assertIsArray($filtered);
        $this->assertCount(5, $filtered); // User 0, 2, 4, 6, 8
    }

    /** @test */
    public function it_reduces_results_efficiently()
    {
        // Create a filter
        $filter = new TestFilter($this->request);

        // Need to mock the lazyEach method to avoid actual DB calls
        $filterMock = m::mock($filter)->makePartial();
        $filterMock->shouldReceive('lazyEach')
            ->once()
            ->with(m::type('Closure'), 500)
            ->andReturnUsing(function ($callback, $chunkSize) {
                // Simulate processing by calling callback with items
                foreach (MockFilterable::all() as $item) {
                    $callback($item);
                }
            });

        // Reduce results to count records
        $count = $filterMock->reduce(function ($carry, $item) {
            return $carry + 1;
        }, 0, 500);

        $this->assertEquals(10, $count);
    }

    /** @test */
    public function it_processes_results_with_cursor()
    {
        // Create a mock builder
        $builderMock = m::mock($this->builder)->makePartial();
        $builderMock->shouldReceive('cursor')
            ->once()
            ->andReturn(MockFilterable::all()->cursor());

        $filter = new TestFilter($this->request);
        $filter->apply($builderMock);

        // Get cursor
        $cursor = $filter->cursor();

        $this->assertInstanceOf(\Generator::class, $cursor);

        // Count items
        $count = 0;
        foreach ($cursor as $item) {
            $count++;
        }

        $this->assertEquals(10, $count);
    }

    /** @test */
    public function it_processes_results_with_chunk()
    {
        // Create a mock builder
        $builderMock = m::mock($this->builder)->makePartial();
        $builderMock->shouldReceive('chunk')
            ->once()
            ->with(5, m::type('Closure'))
            ->andReturnUsing(function ($size, $callback) {
                // Simulate chunking
                $chunks = MockFilterable::all()->chunk($size);
                foreach ($chunks as $chunk) {
                    $callback($chunk);
                }

                return true;
            });

        $filter = new TestFilter($this->request);
        $filter->apply($builderMock);

        // Counters to track chunks and total records
        $chunkCount = 0;
        $recordCount = 0;

        // Process in chunks
        $filter->chunk(5, function ($chunk) use (&$chunkCount, &$recordCount) {
            $chunkCount++;
            $recordCount += $chunk->count();
        });

        $this->assertEquals(2, $chunkCount); // 10 records in chunks of 5
        $this->assertEquals(10, $recordCount);
    }
}
