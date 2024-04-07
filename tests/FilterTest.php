<?php

namespace Filterable\Tests;

use Filterable\Filter;
use Filterable\Tests\Fixtures\MockFilter;
use Filterable\Tests\Fixtures\MockFilterable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Mockery as m;

/**
 * @covers \Filterable\Filter
 */
final class FilterTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        MockFilterable::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    public function testAppliesFiltersDynamicallyBasedOnRequest(): void
    {
        $request = Request::create('/?name=' . urlencode('John Doe'), 'GET');
        $model = new MockFilterable();
        $builder = $model->newQuery();

        $cache = m::mock(Repository::class);
        $cache->shouldReceive('remember')->andReturn($builder);

        // Assuming 'name' filter translates to a method call
        $filter = new MockFilter($request, $cache);
        $filter->setUseCache(false);

        $results = $filter->apply($builder);

        $this->assertEquals(
            $model->newQuery()->where('name', 'LIKE', '%John Doe%')->toSql(),
            $results->toSql()
        );
        $this->assertEquals('John Doe', $results->first()->name);
    }

    public function testAppliesFiltersDynamicallyBasedOnRequestWithCustomMethodNames(): void
    {
        $request = Request::create('/?name=' . urlencode('John Doe'), 'GET');
        $model = new MockFilterable();
        $builder = $model->newQuery();

        $cache = m::mock(Repository::class);
        $cache->shouldReceive('remember')->andReturn($builder);

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
        $filter->setUseCache(false);

        $results = $filter->apply($builder);

        $this->assertEquals(
            $model->newQuery()->where('name', 'LIKE', '%John Doe%')->toSql(),
            $results->toSql()
        );
        $this->assertEquals('John Doe', $results->first()->name);
    }

    public function testHandlesCachingCorrectly(): void
    {
        $request = new Request();
        $cache = m::spy(Repository::class);
        $model = new class () extends Model {};
        $builder = $model->newQuery();

        $filter = new class ($request, $cache) extends Filter {
            protected array $filters = ['test_filter'];

            protected function testFilter($value)
            {
                // Dummy filter application
            }
        };

        $results = $filter->apply($builder);

        // Verify that caching logic is invoked
        $cache->shouldHaveReceived('remember')
            ->once()
            ->with(m::type('string'), m::type('int'), m::type('Closure'))
            ->andReturn($builder);
        $filter->shouldHaveReceived('testFilter')->once();

        $this->assertSame($builder, $results);
        $this->assertEmpty($results->get());
    }

    public function testHandlesCachingCorrectlyWhenDisabled(): void
    {
        $request = new Request();
        $cache = m::spy(Repository::class);
        $model = new class () extends Model {};
        $builder = $model->newQuery();

        $filter = new class ($request, $cache) extends Filter {
            protected array $filters = ['test_filter'];

            protected function testFilter($value)
            {
                // Dummy filter application
            }
        };
        $filter->setUseCache(false);

        $results = $filter->apply($builder);

        // Verify that caching logic is not invoked
        $cache->shouldNotHaveReceived('remember');
        $filter->shouldHaveReceived('testFilter')->once();

        $this->assertSame($builder, $results);
        $this->assertEmpty($results->get());
    }

    public function testHandlesCachingCorrectlyWhenForced(): void
    {
        $request = new Request();
        $cache = m::spy(Repository::class);
        $model = new class () extends Model {};
        $builder = $model->newQuery();

        $filter = new class ($request, $cache) extends Filter {
            protected array $filters = ['test_filter'];

            protected function testFilter($value)
            {
                // Dummy filter application
            }
        };
        $filter->setUseCache(true);

        $results = $filter->apply($builder);

        // Verify that caching logic is invoked
        $cache->shouldHaveReceived('remember')
            ->once()
            ->with(m::type('string'), m::type('int'), m::type('Closure'))
            ->andReturn($builder);
        $filter->shouldHaveReceived('testFilter')->once();

        $this->assertSame($builder, $results);
        $this->assertEmpty($results->get());
    }

    public function testHandlesCachingCorrectlyWhenForcedWithCustomTtl(): void
    {
        $request = new Request();
        $cache = m::spy(Repository::class);
        $model = new class () extends Model {};
        $builder = $model->newQuery();

        $filter = new class ($request, $cache) extends Filter {
            protected array $filters = ['test_filter'];

            protected function testFilter($value)
            {
                // Dummy filter application
            }
        };
        $filter->setUseCache(true);
        $filter->setCacheExpiration(60);

        $results = $filter->apply($builder);

        // Verify that caching logic is invoked
        $cache->shouldHaveReceived('remember')
            ->once()
            ->with(m::type('string'), 60, m::type('Closure'))
            ->andReturn($builder);
        $filter->shouldHaveReceived('testFilter')->once();

        $this->assertSame($builder, $results);
        $this->assertEmpty($results->get());
    }

    public function testHandlesCachingCorrectlyWhenForcedWithCustomKey(): void
    {
        $request = new Request();
        $cache = m::spy(Repository::class);
        $model = new class () extends Model {};
        $builder = $model->newQuery();

        $filter = new class ($request, $cache) extends Filter {
            protected array $filters = ['test_filter'];

            protected function testFilter($value)
            {
                // Dummy filter application
            }
        };
        $filter->setUseCache(true, null, 'custom_key');

        $results = $filter->apply($builder);

        // Verify that caching logic is invoked
        $cache->shouldHaveReceived('remember')
            ->once()
            ->with('custom_key', m::type('int'), m::type('Closure'))
            ->andReturn($builder);
        $filter->shouldHaveReceived('testFilter')->once();

        $this->assertSame($builder, $results);
        $this->assertEmpty($results->get());
    }
}
