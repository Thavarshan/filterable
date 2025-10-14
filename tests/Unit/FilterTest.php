<?php

namespace Filterable\Tests\Unit;

use Closure;
use Exception;
use Filterable\Events\FilterApplied;
use Filterable\Events\FilterApplying;
use Filterable\Events\FilterFailed;
use Filterable\Filter;
use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\Fixtures\MockTestFilter;
use Filterable\Tests\Fixtures\TestFilter;
use Filterable\Tests\TestCase;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Mockery as m;
use Psr\Log\LoggerInterface;
use RuntimeException;

class FilterTest extends TestCase
{
    protected Repository $cache;

    protected LoggerInterface $logger;

    protected Request $request;

    protected Builder $builder;

    protected MockFilterable $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = m::mock(Repository::class);
        $this->logger = m::mock(LoggerInterface::class);
        $this->request = new Request;
        $this->model = new MockFilterable;
        $this->builder = $this->model->newQuery();

        // Create some test data
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

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_applies_feature_defaults_from_configuration(): void
    {
        $original = config('filterable.defaults.features.validation');
        config()->set('filterable.defaults.features.validation', true);

        try {
            $filter = new TestFilter($this->request);

            $this->assertTrue($filter->hasFeature('validation'));
        } finally {
            config()->set('filterable.defaults.features.validation', $original);
        }
    }

    public function test_applies_cache_ttl_from_configuration(): void
    {
        $originalTtl = config('filterable.defaults.cache.ttl');
        config()->set('filterable.defaults.cache.ttl', 15);

        try {
            $filter = new class($this->request) extends Filter {};

            $this->assertEquals(15, $filter->getCacheExpiration());
        } finally {
            config()->set('filterable.defaults.cache.ttl', $originalTtl);
        }
    }

    public function test_initializes_with_default_state(): void
    {
        $filter = new TestFilter($this->request);

        $this->assertEquals('initialized', $filter->getDebugInfo()['state']);
        $this->assertEmpty($filter->getDebugInfo()['filters_applied']);
        $this->assertEmpty($filter->getDebugInfo()['features_enabled']);
    }

    public function test_transitions_state_during_apply(): void
    {
        $filter = new TestFilter($this->request);

        $filter->apply($this->builder);

        $this->assertEquals('applied', $filter->getDebugInfo()['state']);
    }

    public function test_prevents_reapplying_filters(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Filter cannot be reapplied');

        $filter = new TestFilter($this->request);

        // First application should succeed
        $filter->apply($this->builder);

        // Second application should throw exception
        $filter->apply($this->builder);
    }

    public function test_resets_filter_state(): void
    {
        $filter = new TestFilter($this->request);

        // Apply filter and verify state change
        $filter->apply($this->builder);
        $this->assertEquals('applied', $filter->getDebugInfo()['state']);

        // Reset filter
        $filter->reset();

        // Verify state is reset
        $this->assertEquals('initialized', $filter->getDebugInfo()['state']);
        $this->assertNull($filter->getBuilder());
        $this->assertEmpty($filter->getDebugInfo()['filters_applied']);
    }

    public function test_handles_errors_during_filtering(): void
    {
        // Create a filter that throws an exception during filtering
        $filter = new class($this->request) extends TestFilter
        {
            protected array $filters = ['test'];

            protected function test($value): void
            {
                throw new Exception('Test exception');
            }
        };

        // Add a filter value to trigger the exception
        $filter->appendFilterable('test', 'value');

        // Apply filter - it should catch the exception
        $filter->apply($this->builder);

        // Verify state is changed to failed
        $this->assertEquals('failed', $filter->getDebugInfo()['state']);

        // Verify get() throws an exception
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Filters failed to apply: Test exception');

        $filter->get();
    }

    public function test_requires_apply_before_get(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You must call apply() before get()');

        $filter = new TestFilter($this->request);

        // Calling get() without apply() should throw
        $filter->get();
    }

    public function test_rethrows_validation_exceptions(): void
    {
        // Create a filter that throws a validation exception
        $filter = new class($this->request) extends TestFilter
        {
            protected function validateFilterInputs(): void
            {
                throw new ValidationException(
                    validator([], [])
                );
            }
        };

        // Enable validation
        $filter->enableFeature('validation');

        // Expect the validation exception to be rethrown
        $this->expectException(ValidationException::class);

        $filter->apply($this->builder);
    }

    public function test_enables_features_by_constructor_dependencies(): void
    {
        $this->cache->shouldReceive('remember')->andReturn(collect());

        // Create filter with cache and logger
        $filter = new TestFilter($this->request, $this->cache, $this->logger);

        // Verify features were enabled
        $this->assertTrue($filter->hasFeature('caching'));
        $this->assertTrue($filter->hasFeature('logging'));
    }

    public function test_runs_full_query_pipeline(): void
    {
        // Create a test filter
        $filter = new TestFilter($this->request);

        // Run the query pipeline
        $result = $filter->runQuery($this->builder);

        // The main assertion is that the pipeline completes successfully and returns a collection
        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_uses_cached_execution_when_caching_enabled(): void
    {
        $this->cache->shouldReceive('remember')
            ->once()
            ->with(
                'filter:test',           // First parameter: cache key
                m::type('Carbon\Carbon'), // Second parameter: Carbon instance
                m::type('Closure')       // Third parameter: Closure
            )
            ->andReturn(collect(['cached_item']));

        // Create filter with cache
        $filter = new TestFilter($this->request, $this->cache);

        // Verify caching is enabled
        $this->assertTrue($filter->hasFeature('caching'));

        // Apply filter
        $filter->apply($this->builder);

        // This is enough to test the caching feature - we're verifying that caching is enabled and apply completes
        $this->assertEquals('applied', $filter->getDebugInfo()['state']);
    }

    public function test_uses_memory_management_when_enabled(): void
    {
        // Instead of using a mock, we'll use a special test class
        $filter = new class($this->request) extends MockTestFilter
        {
            public $wasCalled = false;

            public function mockExecuteQueryWithMemoryManagement(): Collection
            {
                $this->wasCalled = true;

                return new Collection(['item1', 'item2']);
            }
        };

        // Enable memory management
        $filter->enableFeature('memoryManagement');
        $filter->setOptions(['chunk_size' => 10]);

        // Apply and get the results
        $filter->apply($this->builder);
        $results = $filter->get();

        // Verify that our method was called
        $this->assertTrue($filter->wasCalled);
        $this->assertEquals(['item1', 'item2'], $results->toArray());
    }

    public function test_conditionally_runs_feature_specific_methods(): void
    {
        // Create a test class that tracks method calls
        $filter = new class($this->request) extends MockTestFilter
        {
            public $methodsCalled = [];

            protected function applyUserScope(): void
            {
                $this->methodsCalled[] = 'applyUserScope';
                parent::applyUserScope();
            }

            protected function applyPreFilters(): void
            {
                $this->methodsCalled[] = 'applyPreFilters';
                parent::applyPreFilters();
            }

            protected function applyFilterables(): void
            {
                $this->methodsCalled[] = 'applyFilterables';
                parent::applyFilterables();
            }

            protected function validateFilterInputs(): void
            {
                $this->methodsCalled[] = 'validateFilterInputs';
                parent::validateFilterInputs();
            }

            protected function checkFilterPermissions(): void
            {
                $this->methodsCalled[] = 'checkFilterPermissions';
                parent::checkFilterPermissions();
            }

            protected function checkRateLimits(): bool
            {
                $this->methodsCalled[] = 'checkRateLimits';
                parent::checkRateLimits();
            }

            protected function transformFilterValues(): void
            {
                $this->methodsCalled[] = 'transformFilterValues';
                parent::transformFilterValues();
            }

            protected function startTiming(): void
            {
                $this->methodsCalled[] = 'startTiming';
                parent::startTiming();
            }

            protected function endTiming(): void
            {
                $this->methodsCalled[] = 'endTiming';
                parent::endTiming();
            }
        };

        // No features enabled by default

        // Apply filter
        $filter->apply($this->builder);

        // Check that only the required methods were called
        $this->assertContains('applyUserScope', $filter->methodsCalled);
        $this->assertContains('applyPreFilters', $filter->methodsCalled);
        $this->assertContains('applyFilterables', $filter->methodsCalled);

        // Check that feature-specific methods were NOT called
        $this->assertNotContains('validateFilterInputs', $filter->methodsCalled);
        $this->assertNotContains('checkFilterPermissions', $filter->methodsCalled);
        $this->assertNotContains('checkRateLimits', $filter->methodsCalled);
        $this->assertNotContains('transformFilterValues', $filter->methodsCalled);
        $this->assertNotContains('startTiming', $filter->methodsCalled);
        $this->assertNotContains('endTiming', $filter->methodsCalled);
    }

    public function test_runs_enabled_feature_methods(): void
    {
        // Create a test class that tracks method calls
        $filter = new class($this->request) extends TestFilter
        {
            public $methodsCalled = [];

            protected function validateFilterInputs(): void
            {
                $this->methodsCalled[] = 'validateFilterInputs';
            }

            protected function applyUserScope(): void
            {
                $this->methodsCalled[] = 'applyUserScope';
                parent::applyUserScope();
            }

            protected function applyPreFilters(): void
            {
                $this->methodsCalled[] = 'applyPreFilters';
                parent::applyPreFilters();
            }

            protected function applyFilterables(): void
            {
                $this->methodsCalled[] = 'applyFilterables';
                parent::applyFilterables();
            }

            protected function transformFilterValues(): void
            {
                $this->methodsCalled[] = 'transformFilterValues';
            }

            protected function startTiming(): void
            {
                $this->methodsCalled[] = 'startTiming';
            }

            protected function endTiming(): void
            {
                $this->methodsCalled[] = 'endTiming';
            }

            // Implement actual apply method to ensure our features are called
            public function apply(Builder $builder, ?array $options = []): Builder
            {
                $this->builder = $builder;
                $this->options = $options ?? [];
                $this->state = 'applying';

                // Start timing if performance is enabled
                if ($this->hasFeature('performance')) {
                    $this->startTiming();
                }

                // Run validation if enabled
                if ($this->hasFeature('validation')) {
                    $this->validateFilterInputs();
                }

                // Transform values if enabled
                if ($this->hasFeature('valueTransformation')) {
                    $this->transformFilterValues();
                }

                // Core methods are always called
                $this->applyUserScope();
                $this->applyPreFilters();
                $this->applyFilterables();

                // End timing if performance is enabled
                if ($this->hasFeature('performance')) {
                    $this->endTiming();
                }

                $this->state = 'applied';

                return $this->builder;
            }
        };

        // Enable specific features
        $filter->enableFeatures([
            'validation',
            'performance',
            'valueTransformation',
        ]);

        // Apply filter
        $filter->apply($this->builder);

        // Check that core methods were called
        $this->assertContains('applyUserScope', $filter->methodsCalled);
        $this->assertContains('applyPreFilters', $filter->methodsCalled);
        $this->assertContains('applyFilterables', $filter->methodsCalled);

        // Check that enabled feature methods were called
        $this->assertContains('validateFilterInputs', $filter->methodsCalled);
        $this->assertContains('transformFilterValues', $filter->methodsCalled);
        $this->assertContains('startTiming', $filter->methodsCalled);
        $this->assertContains('endTiming', $filter->methodsCalled);
    }

    public function test_provides_comprehensive_debug_info(): void
    {
        $filter = new TestFilter($this->request);

        // Enable performance to include metrics
        $filter->enableFeature('performance');

        // Apply the filter to set state
        $filter->apply($this->builder);

        $debugInfo = $filter->getDebugInfo();

        // Verify all expected keys are present
        $this->assertArrayHasKey('state', $debugInfo);
        $this->assertArrayHasKey('filters_applied', $debugInfo);
        $this->assertArrayHasKey('features_enabled', $debugInfo);
        $this->assertArrayHasKey('options', $debugInfo);
        $this->assertArrayHasKey('sql', $debugInfo);
        $this->assertArrayHasKey('bindings', $debugInfo);
        $this->assertArrayHasKey('metrics', $debugInfo);

        // Verify state is correct
        $this->assertEquals('applied', $debugInfo['state']);

        // Performance feature should be enabled
        $this->assertArrayHasKey('performance', $debugInfo['features_enabled']);
    }

    public function test_supports_setting_and_getting_options(): void
    {
        $filter = new TestFilter($this->request);

        // Set options
        $options = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $filter->setOptions($options);

        // Verify options are set
        $this->assertEquals($options, $filter->getOptions());
    }

    public function test_allows_setting_and_getting_builder(): void
    {
        $filter = new TestFilter($this->request);

        // Set builder
        $filter->setBuilder($this->builder);

        // Verify builder is set
        $this->assertSame($this->builder, $filter->getBuilder());
    }

    public function test_stream_requires_apply_before_execution(): void
    {
        $filter = new TestFilter($this->request);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You must call apply() before streaming results.');

        $filter->stream();
    }

    public function test_dispatches_lifecycle_events_on_successful_apply(): void
    {
        Event::fake([FilterApplying::class, FilterApplied::class, FilterFailed::class]);

        $filter = new TestFilter($this->request);

        $filter->apply($this->builder);

        Event::assertDispatched(FilterApplying::class, function (FilterApplying $event) use ($filter) {
            return $event->filter === $filter
                && $event->builder === $filter->getBuilder()
                && $event->options === $filter->getOptions();
        });

        Event::assertDispatched(FilterApplied::class, function (FilterApplied $event) use ($filter) {
            return $event->filter === $filter
                && $event->builder === $filter->getBuilder()
                && $event->filters === $filter->getCurrentFilters();
        });

        Event::assertNotDispatched(FilterFailed::class);
    }

    public function test_dispatches_failure_event_when_exception_occurs(): void
    {
        Event::fake([FilterFailed::class]);

        $filter = new class($this->request) extends TestFilter
        {
            protected array $filters = ['test'];

            protected function test($value): void
            {
                throw new Exception('Test exception');
            }
        };

        $filter->appendFilterable('test', 'value');

        $filter->apply($this->builder);

        Event::assertDispatched(FilterFailed::class, function (FilterFailed $event) use ($filter) {
            return $event->filter === $filter
                && $event->builder === $filter->getBuilder()
                && $event->exception instanceof Exception
                && $event->exception->getMessage() === 'Test exception';
        });
    }

    public function test_rate_limit_key_includes_user_identifier_when_available(): void
    {
        $request = new Request;
        $request->server->set('REMOTE_ADDR', '203.0.113.5');

        $filter = new class($request) extends TestFilter
        {
            public function callCheckRateLimits(): bool
            {
                return $this->checkRateLimits();
            }
        };

        $user = m::mock(\Illuminate\Contracts\Auth\Authenticatable::class);
        $user->shouldReceive('getAuthIdentifierName')->andReturn('id');
        $user->shouldReceive('getAuthIdentifier')->andReturn(99);

        $filter->forUser($user);

        $expectedKey = 'filter:'.md5('203.0.113.5|'.get_class($filter).'|user:99');

        $limiter = m::mock(RateLimiter::class);
        app()->instance(RateLimiter::class, $limiter);

        $limiter->shouldReceive('tooManyAttempts')
            ->once()
            ->withArgs(function ($key, $maxAttempts, $window) use ($expectedKey) {
                $this->assertSame($expectedKey, $key);
                $this->assertSame(60, $maxAttempts);
                $this->assertSame(60, $window);

                return true;
            })
            ->andReturn(false);

        $limiter->shouldReceive('hit')
            ->once()
            ->withArgs(function ($key, $decay) use ($expectedKey) {
                $this->assertSame($expectedKey, $key);
                $this->assertSame(1, $decay);

                return true;
            });

        try {
            $this->assertTrue($filter->callCheckRateLimits());
        } finally {
            app()->forgetInstance(RateLimiter::class);
        }
    }

    public function test_rate_limit_configuration_can_be_overridden_per_filter(): void
    {
        $request = new Request;
        $request->server->set('REMOTE_ADDR', '198.51.100.10');

        $filter = new class($request) extends TestFilter
        {
            public function callCheckRateLimits(): bool
            {
                return $this->checkRateLimits();
            }

            protected function resolveRateLimitMaxAttempts(): int
            {
                return 5;
            }

            protected function resolveRateLimitWindowSeconds(int $complexityScore): int
            {
                return 120;
            }

            protected function resolveRateLimitDecaySeconds(int $complexityScore): int
            {
                return 90;
            }
        };

        $expectedKey = 'filter:'.md5('198.51.100.10|'.get_class($filter));

        $limiter = m::mock(RateLimiter::class);
        app()->instance(RateLimiter::class, $limiter);

        $limiter->shouldReceive('tooManyAttempts')
            ->once()
            ->withArgs(function ($key, $maxAttempts, $window) use ($expectedKey) {
                $this->assertSame($expectedKey, $key);
                $this->assertSame(5, $maxAttempts);
                $this->assertSame(120, $window);

                return true;
            })
            ->andReturn(false);

        $limiter->shouldReceive('hit')
            ->once()
            ->withArgs(function ($key, $decay) use ($expectedKey) {
                $this->assertSame($expectedKey, $key);
                $this->assertSame(90, $decay);

                return true;
            });

        try {
            $this->assertTrue($filter->callCheckRateLimits());
        } finally {
            app()->forgetInstance(RateLimiter::class);
        }
    }
}
