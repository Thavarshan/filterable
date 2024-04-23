<?php

namespace Filterable\Tests;

use Carbon\Carbon;
use Filterable\Filter;
use Filterable\Tests\Fixtures\MockFilter;
use Filterable\Tests\Fixtures\MockFilterable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Mockery as m;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;

/**
 * Class FilterTest.
 *
 * @covers \Filterable\Filter
 */
final class FilterTest extends TestCase
{
    /**
     * The cache handler instance.
     *
     * @var \Illuminate\Contracts\Cache\Repository|\Mockery\MockInterface
     */
    protected Repository|MockInterface $cache;

    /**
     * The Logger instance.
     *
     * @var \Psr\Log\LoggerInterface|\Mockery\MockInterface
     */
    protected LoggerInterface|MockInterface $logger;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setupLogger();

        MockFilterable::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        MockFilterable::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        m::close();

        Filter::disableCaching();

        parent::tearDown();
    }

    public function testAppliesFiltersDynamicallyBasedOnRequest(): void
    {
        Filter::disableCaching();

        $request = Request::create('/?name=' . urlencode('John Doe'), 'GET');
        $model = new MockFilterable();
        $builder = $model->newQuery();

        $cache = m::mock(Repository::class);
        $cache->shouldNotReceive('remember')->andReturn($builder);

        // Assuming 'name' filter translates to a method call
        $filter = new MockFilter($request, $cache);

        $results = $filter->apply($builder);

        $this->assertEquals(
            $model->newQuery()->where('name', 'LIKE', '%John Doe%')->toSql(),
            $results->toSql()
        );
        $this->assertCount(1, $results->get());
        $this->assertEquals('John Doe', $results->first()->name);
    }

    public function testAppliesFiltersManuallyThroughModel(): void
    {
        Filter::disableCaching();

        $request = Request::create('/?name=' . urlencode('John Doe'), 'GET');
        $model = new MockFilterable();
        $builder = $model->newQuery();

        $cache = m::mock(Repository::class);
        $cache->shouldNotReceive('remember')->andReturn($builder);

        // Assuming 'name' filter translates to a method call
        $filter = new MockFilter($request, $cache);

        $results = $model->filter($filter);

        $this->assertEquals(
            $model->newQuery()->where('name', 'LIKE', '%John Doe%')->toSql(),
            $results->toSql()
        );
        $this->assertCount(1, $results->get());
        $this->assertEquals('John Doe', $results->first()->name);
    }

    public function testAppliesFiltersDynamicallyBasedOnRequestWithCustomMethodNames(): void
    {
        Filter::disableCaching();

        $request = Request::create('/?name=' . urlencode('Jane Doe'), 'GET');
        $model = new MockFilterable();
        $builder = $model->newQuery();

        $cache = m::mock(Repository::class);
        $cache->shouldNotReceive('remember')->andReturn($builder);

        // Assuming 'name' filter translates to a method call
        $filter = new class ($request, $cache) extends Filter {
            protected array $filterMethodMap = [
                'name' => 'filterByName',
            ];

            public function filterByName($name)
            {
                return $this->builder->where('name', 'LIKE', "%{$name}%");
            }
        };

        $results = $filter->apply($builder);

        $this->assertEquals(
            $model->newQuery()->where('name', 'LIKE', '%Jane Doe%')->toSql(),
            $results->toSql()
        );
        $this->assertCount(1, $results->get());
        $this->assertEquals('Jane Doe', $results->first()->name);
    }

    public function testHandlesCachingCorrectly(): void
    {
        Filter::enableCaching(true);

        $request = new Request();
        $cache = m::spy(Repository::class);
        $model = new MockFilterable();
        $builder = $model->newQuery();

        // Verify that caching logic is invoked
        $cache->shouldReceive('remember')->once();

        $filter = new class ($request, $cache) extends Filter {
            protected array $filters = ['test_filter'];

            public function __construct($request, $cache)
            {
                parent::__construct($request, $cache);
            }

            protected function testFilter($value)
            {
                // Dummy filter application
            }
        };

        $results = $filter->apply($builder);

        // Verify that caching logic was invoked
        $cache->shouldHaveReceived('remember')->once();

        $this->assertSame($builder, $results);
    }

    public function testHandlesCachingCorrectlyWhenDisabled(): void
    {
        Filter::disableCaching();

        $request = new Request();
        $cache = m::spy(Repository::class);
        $model = new MockFilterable();
        $builder = $model->newQuery();

        // Verify that caching logic is not invoked
        $cache->shouldNotHaveReceived('remember');

        $filter = new class ($request, $cache) extends Filter {
            protected array $filters = ['test_filter'];

            protected function testFilter($value)
            {
                // Dummy filter application
            }
        };

        $results = $filter->apply($builder);

        $this->assertSame($builder, $results);
        $this->assertNotEmpty($results->get());
    }

    public function testHandlesCachingCorrectlyWhenForced(): void
    {
        Filter::enableCaching(true);

        $request = new Request();
        $cache = m::spy(Repository::class);
        $model = new MockFilterable();
        $builder = $model->newQuery();

        // Verify that caching logic is invoked
        $cache->shouldReceive('remember')->once();

        $filter = new class ($request, $cache) extends Filter {
            protected array $filters = ['test_filter'];

            protected function testFilter($value)
            {
                // Dummy filter application
            }
        };

        $results = $filter->apply($builder);

        $this->assertSame($builder, $results);
        $this->assertNotEmpty($results->get());
    }

    public function testHandlesCachingCorrectlyWhenForcedWithCustomTtl(): void
    {
        Filter::enableCaching(true);

        $request = new Request();
        $cache = m::spy(Repository::class);
        $model = new MockFilterable();
        $builder = $model->newQuery();

        $customTtl = Carbon::now()->addMinutes(60);

        // Verify that caching logic is invoked
        $cache->shouldReceive('remember')->once();

        $filter = new class ($request, $cache) extends Filter {
            protected array $filters = ['test_filter'];

            protected function testFilter($value)
            {
                // Dummy filter application
            }
        };

        $filter->setCacheExpiration(60);

        $results = $filter->apply($builder);

        $this->assertSame($builder, $results);
        $this->assertNotEmpty($results->get());
    }

    public function testClearsCacheCorrectly(): void
    {
        Filter::enableCaching(true);

        $request = new Request();
        $cache = m::mock(Repository::class);
        $cache->shouldReceive('forget')->once();

        $filter = new class ($request, $cache) extends Filter {
            // Custom implementation or use existing methods.
        };

        $filter->clearCache();

        // Assertions to ensure cache forget was called.
        $cache->shouldHaveReceived('forget')->once();

        $this->assertTrue(true);
    }

    public function testAppliesPreFiltersCorrectly(): void
    {
        Filter::enableCaching();

        $request = new Request();
        $cache = m::mock(Repository::class);
        $model = new MockFilterable();
        $builder = $model->newQuery();

        // Verify that caching logic is invoked
        $cache->shouldReceive('remember')->once();

        $filter = new class ($request, $cache) extends Filter {
            public function applyPreFilters(): void
            {
                $this->builder->where('active', 1);
            }
        };

        // $filter->enableCaching(false);

        $filter->apply($builder);

        $this->assertEquals(
            $model->newQuery()->where('active', 1)->toSql(),
            $filter->getBuilder()->toSql()
        );
    }

    public function testSetsAndGetsOptionsCorrectly(): void
    {
        $request = new Request();
        $cache = m::mock(Repository::class);

        $filter = new class ($request, $cache) extends Filter {
            // Custom implementation or use existing methods.
        };

        $options = ['option1' => 'value1'];

        $filter->setOptions($options);

        $this->assertSame($options, $filter->getOptions());
    }

    public function testLoggingWhenFiltersApplied(): void
    {
        Filter::disableCaching();
        Filter::enableLogging();

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with('Applying filter method: name', [
                'filter' => 'name',
                'value' => 'John Doe'
            ]);

        $cache = m::mock(Repository::class);
        $request = Request::create('/?name=' . urlencode('John Doe'), 'GET');
        $filter = new MockFilter($request, $cache, $this->logger);
        $model = new MockFilterable();
        $builder = $model->newQuery();

        $results = $filter->apply($builder);

        // Assertions to check the results as before
        $this->assertEquals(
            $model->newQuery()->where('name', 'LIKE', '%John Doe%')->toSql(),
            $results->toSql()
        );
        $this->assertCount(1, $results->get());
        $this->assertEquals('John Doe', $results->first()->name);
    }

    public function testNoLoggingWhenLoggingDisabled(): void
    {
        Filter::disableCaching();
        Filter::disableLogging();

        // Logger should not receive any calls
        $this->logger->shouldNotReceive('info');

        $cache = m::mock(Repository::class);
        $request = Request::create('/?name=' . urlencode('John Doe'), 'GET');
        $filter = new MockFilter($request, $cache, $this->logger);
        $model = new MockFilterable();
        $builder = $model->newQuery();

        $results = $filter->apply($builder);

        // Assertions to check the results as before
        $this->assertEquals(
            $model->newQuery()->where('name', 'LIKE', '%John Doe%')->toSql(),
            $results->toSql()
        );
        $this->assertCount(1, $results->get());
        $this->assertEquals('John Doe', $results->first()->name);
    }

    public function testEnableAndDisableLoggingChecks(): void
    {
        Filter::enableLogging();
        $this->assertTrue(Filter::shouldLog());

        Filter::disableLogging();
        $this->assertFalse(Filter::shouldLog());
    }

    /**
     * Setup the Logger mock.
     *
     * @return void
     */
    protected function setupLogger(): void
    {
        // Mock the Logger
        $this->logger = m::mock(LoggerInterface::class);
    }
}
