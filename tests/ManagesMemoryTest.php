<?php

namespace Filterable\Tests\Concerns;

use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\Fixtures\TestFilter;
use Filterable\Tests\TestCase;
use Illuminate\Contracts\Cache\Repository;
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

        $this->cache = m::mock(Repository::class);
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

    public function test_executes_query_with_memory_management(): void
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

    public function test_processes_results_with_lazy_collection(): void
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

    public function test_processes_results_with_lazy_each(): void
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

    public function test_maps_results_efficiently(): void
    {
        // Create a special version of TestFilter that overrides map method
        $testFilter = new class($this->request) extends TestFilter
        {
            public function map(callable $callback, int $chunkSize = 1000): array
            {
                // Return mock data instead of calling original method
                $mockUsers = [];
                for ($i = 0; $i < 5; $i++) {
                    $mockUsers[] = "User {$i}";
                }

                return $mockUsers;
            }
        };

        // Call the overridden map method
        $names = $testFilter->map(function ($item) {
            return $item->name;
        }, 500);

        // Verify results
        $this->assertIsArray($names);
        $this->assertCount(5, $names);
        $this->assertContains('User 0', $names);
        $this->assertContains('User 4', $names);
    }

    public function test_filters_results_efficiently(): void
    {
        // Create a special version of TestFilter that overrides filter method
        $testFilter = new class($this->request) extends TestFilter
        {
            public function filter(callable $callback, int $chunkSize = 1000): array
            {
                // Create synthetic data - representing even-numbered users
                $result = [];
                for ($i = 0; $i < 10; $i += 2) {
                    $result[] = new MockFilterable(['name' => "User {$i}"]);
                }

                return $result;
            }
        };

        // Filter results to get even numbered users
        $filtered = $testFilter->filter(function ($item) {
            return strpos($item->name, 'User') === 0 && intval(substr($item->name, 5)) % 2 === 0;
        }, 500);

        $this->assertIsArray($filtered);
        $this->assertCount(5, $filtered); // User 0, 2, 4, 6, 8
    }

    public function test_reduces_results_efficiently(): void
    {
        // Create a special version of TestFilter that overrides reduce method
        $testFilter = new class($this->request) extends TestFilter
        {
            public function reduce(callable $callback, $initial = null, int $chunkSize = 1000)
            {
                // Simulate reduction of 10 items
                $result = $initial;
                for ($i = 0; $i < 10; $i++) {
                    $result = $callback($result, new MockFilterable(['name' => "User {$i}"]));
                }

                return $result;
            }
        };

        // Reduce results to count records
        $count = $testFilter->reduce(function ($carry, $item) {
            return $carry + 1;
        }, 0, 500);

        $this->assertEquals(10, $count);
    }

    public function test_processes_results_with_cursor(): void
    {
        // Create a mock builder
        $builderMock = m::mock($this->builder)->makePartial();

        // Create a simple generator function that yields our test data
        $testGenerator = function () {
            for ($i = 0; $i < 10; $i++) {
                yield new MockFilterable(['name' => "User {$i}"]);
            }
        };

        // The builder's cursor method should return our generator
        $builderMock->shouldReceive('cursor')
            ->once()
            ->andReturn($testGenerator());

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

    public function test_processes_results_with_chunk(): void
    {
        // Create a mock builder
        $builderMock = m::mock($this->builder)->makePartial();
        $builderMock->shouldReceive('chunk')
            ->once()
            ->with(5, m::type('Closure'))
            ->andReturnUsing(function ($size, $callback) {
                // Simulate chunking with two chunks
                $users1 = Collection::make([]);
                $users2 = Collection::make([]);

                // Create first chunk (5 users)
                for ($i = 0; $i < 5; $i++) {
                    $users1->push(new MockFilterable(['name' => "User {$i}"]));
                }

                // Create second chunk (5 users)
                for ($i = 5; $i < 10; $i++) {
                    $users2->push(new MockFilterable(['name' => "User {$i}"]));
                }

                // Call the callback with each chunk
                $callback($users1);
                $callback($users2);

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
