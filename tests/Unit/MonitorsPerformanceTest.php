<?php

namespace Filterable\Tests\Unit;

use Filterable\Tests\Fixtures\TestFilter;
use Filterable\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Mockery as m;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use ReflectionProperty;

class MonitorsPerformanceTest extends TestCase
{
    protected $request;

    protected $filter;

    protected $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new Request;
        $this->logger = m::mock(LoggerInterface::class);
        $this->filter = new TestFilter($this->request, null, $this->logger);

        // Enable performance monitoring
        $this->filter->enableFeature('performance');
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_can_add_custom_metric(): void
    {
        // Add a custom metric
        $result = $this->filter->addMetric('test_metric', 'test_value');

        // Check fluent interface
        $this->assertSame($this->filter, $result);

        // Get metrics
        $metrics = $this->filter->getMetrics();

        // Verify the custom metric was added
        $this->assertArrayHasKey('test_metric', $metrics);
        $this->assertEquals('test_value', $metrics['test_metric']);
    }

    public function test_can_add_multiple_metrics(): void
    {
        // Add multiple metrics
        $this->filter->addMetric('metric1', 'value1');
        $this->filter->addMetric('metric2', 100);
        $this->filter->addMetric('metric3', ['key' => 'value']);

        // Get metrics
        $metrics = $this->filter->getMetrics();

        // Verify all metrics were added
        $this->assertCount(3, $metrics);
        $this->assertEquals('value1', $metrics['metric1']);
        $this->assertEquals(100, $metrics['metric2']);
        $this->assertEquals(['key' => 'value'], $metrics['metric3']);
    }

    public function test_start_timing_sets_start_time(): void
    {
        // Reset startTime to ensure it's null
        $startTimeProperty = new ReflectionProperty($this->filter, 'startTime');
        $startTimeProperty->setAccessible(true);
        $startTimeProperty->setValue($this->filter, null);

        // Verify startTime is null before calling startTiming
        $this->assertNull($startTimeProperty->getValue($this->filter));

        // Call startTiming
        $startTimingMethod = new ReflectionMethod($this->filter, 'startTiming');
        $startTimingMethod->setAccessible(true);
        $startTimingMethod->invoke($this->filter);

        // Verify startTime is set
        $this->assertNotNull($startTimeProperty->getValue($this->filter));
        $this->assertIsFloat($startTimeProperty->getValue($this->filter));
    }

    public function test_end_timing_sets_execution_time_metric(): void
    {
        // Set a known startTime for predictable testing
        $startTimeProperty = new ReflectionProperty($this->filter, 'startTime');
        $startTimeProperty->setAccessible(true);
        $startTimeProperty->setValue($this->filter, microtime(true) - 1.0); // 1 second ago

        // For this test, we don't want to log
        $this->filter->disableFeature('logging');

        // Call endTiming
        $endTimingMethod = new ReflectionMethod($this->filter, 'endTiming');
        $endTimingMethod->setAccessible(true);
        $endTimingMethod->invoke($this->filter);

        // Verify endTime and execution_time metric are set
        $endTimeProperty = new ReflectionProperty($this->filter, 'endTime');
        $endTimeProperty->setAccessible(true);
        $this->assertNotNull($endTimeProperty->getValue($this->filter));

        // Check the execution_time metric
        $metrics = $this->filter->getMetrics();
        $this->assertArrayHasKey('execution_time', $metrics);
        $this->assertIsFloat($metrics['execution_time']);

        // Since we set startTime to 1 second ago, execution_time should be approximately 1.0
        // We use assertGreaterThan and assertLessThan to allow for slight variations
        $this->assertGreaterThan(0.9, $metrics['execution_time']);
        $this->assertLessThan(1.1, $metrics['execution_time']);
    }

    public function test_end_timing_logs_information_when_logging_enabled(): void
    {
        // Set a known startTime
        $startTimeProperty = new ReflectionProperty($this->filter, 'startTime');
        $startTimeProperty->setAccessible(true);
        $startTimeProperty->setValue($this->filter, microtime(true) - 0.5); // 0.5 seconds ago

        // Enable logging
        $this->filter->enableFeature('logging');

        // Expect a log call
        $this->logger->shouldReceive('info')
            ->once()
            ->with('Filter executed', m::on(function ($context) {
                return isset($context['execution_time']) &&
                       isset($context['memory_usage']) &&
                       isset($context['filter_count']);
            }));

        // Call endTiming
        $endTimingMethod = new ReflectionMethod($this->filter, 'endTiming');
        $endTimingMethod->setAccessible(true);
        $endTimingMethod->invoke($this->filter);

        // Verify that the log method was called (using Mockery's verification)
        $this->assertTrue(true, 'End timing logged information');
    }

    public function test_end_timing_does_not_log_when_logging_disabled(): void
    {
        // Set a known startTime
        $startTimeProperty = new ReflectionProperty($this->filter, 'startTime');
        $startTimeProperty->setAccessible(true);
        $startTimeProperty->setValue($this->filter, microtime(true) - 0.5);

        // Disable logging
        $this->filter->disableFeature('logging');

        // Expect no log calls
        $this->logger->shouldNotReceive('info');

        // Call endTiming
        $endTimingMethod = new ReflectionMethod($this->filter, 'endTiming');
        $endTimingMethod->setAccessible(true);
        $endTimingMethod->invoke($this->filter);

        // Verify that the log method was not called (using Mockery's verification)
        $this->assertTrue(true, 'End timing did not log when disabled');
    }

    public function test_get_execution_time_returns_correct_value(): void
    {
        // Directly set the execution_time metric
        $this->filter->addMetric('execution_time', 1.23);

        // Call getExecutionTime
        $executionTime = $this->filter->getExecutionTime();

        // Verify the correct value is returned
        $this->assertEquals(1.23, $executionTime);
    }

    public function test_get_execution_time_returns_null_when_not_set(): void
    {
        // Ensure execution_time metric is not set
        $metricsProperty = new ReflectionProperty($this->filter, 'metrics');
        $metricsProperty->setAccessible(true);
        $metricsProperty->setValue($this->filter, []);

        // Call getExecutionTime
        $executionTime = $this->filter->getExecutionTime();

        // Verify null is returned
        $this->assertNull($executionTime);
    }

    public function test_performance_monitoring_in_filter_application(): void
    {
        // Create a mock builder
        $builder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
        $builder->shouldReceive('get')->andReturn(collect());

        // Mock the Filter class to verify startTiming and endTiming are called
        $filterMock = m::mock(TestFilter::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $filterMock->shouldReceive('startTiming')
            ->once()
            ->andReturnNull();

        $filterMock->shouldReceive('endTiming')
            ->once()
            ->andReturnNull();

        // Enable performance monitoring
        $filterMock->enableFeature('performance');

        // Apply the filter
        $filterMock->apply($builder);

        // Verify the timing methods were called
        $this->assertTrue(true, 'Performance monitoring was invoked during filter application');
    }

    public function test_performance_monitoring_not_triggered_when_disabled(): void
    {
        // Create a mock builder
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('get')->andReturn(collect());

        // Mock the Filter class to verify startTiming and endTiming are NOT called
        $filterMock = m::mock(TestFilter::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $filterMock->shouldReceive('startTiming')
            ->never();

        $filterMock->shouldReceive('endTiming')
            ->never();

        // Disable performance monitoring
        $filterMock->disableFeature('performance');

        // Apply the filter
        $filterMock->apply($builder);

        // Verify the timing methods were not called
        $this->assertTrue(true, 'Performance monitoring was not triggered when disabled');
    }
}
