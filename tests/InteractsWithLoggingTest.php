<?php

namespace Filterable\Tests\Concerns;

use Filterable\Tests\Fixtures\TestFilter;
use Filterable\Tests\TestCase;
use Illuminate\Http\Request;
use Mockery as m;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use ReflectionProperty;

class InteractsWithLoggingTest extends TestCase
{
    protected $request;

    protected $logger;

    protected $filter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new Request;
        $this->logger = m::mock(LoggerInterface::class);
        $this->filter = new TestFilter($this->request, null, $this->logger);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_can_set_logger(): void
    {
        // Create a new logger instance
        $newLogger = m::mock(LoggerInterface::class);

        // Set the logger
        $result = $this->filter->setLogger($newLogger);

        // Check fluent interface
        $this->assertSame($this->filter, $result);

        // Verify logger was set correctly using reflection
        $reflectionProperty = new ReflectionProperty($this->filter, 'logger');
        $reflectionProperty->setAccessible(true);
        $this->assertSame($newLogger, $reflectionProperty->getValue($this->filter));
    }

    public function test_can_get_logger(): void
    {
        // Call getLogger
        $logger = $this->filter->getLogger();

        // Verify it returns the logger instance we set in setUp
        $this->assertSame($this->logger, $logger);
    }

    public function test_falls_back_to_app_container_when_logger_not_set(): void
    {
        // Create a filter without a logger
        $filter = new TestFilter($this->request);

        // Mock the application container to return our mock logger
        $this->app->instance(LoggerInterface::class, $this->logger);

        // Call getLogger - it should get the logger from the container
        $logger = $filter->getLogger();

        // Verify it returns our mocked logger from the container
        $this->assertSame($this->logger, $logger);
    }

    public function test_logs_info_when_logging_enabled(): void
    {
        // Enable logging feature
        $this->filter->enableFeature('logging');

        // Expect a call to the logger's info method
        $this->logger->shouldReceive('info')
            ->once()
            ->with('Test info message', ['key' => 'value']);

        // Call logInfo through reflection
        $method = new ReflectionMethod($this->filter, 'logInfo');
        $method->setAccessible(true);
        $method->invoke($this->filter, 'Test info message', ['key' => 'value']);

        // Verify using mockery expectations
        $this->assertTrue(true, 'Info was logged correctly');
    }

    public function test_does_not_log_info_when_logging_disabled(): void
    {
        // Disable logging feature
        $this->filter->disableFeature('logging');

        // Expect no calls to the logger's info method
        $this->logger->shouldNotReceive('info');

        // Call logInfo through reflection
        $method = new ReflectionMethod($this->filter, 'logInfo');
        $method->setAccessible(true);
        $method->invoke($this->filter, 'Test info message', ['key' => 'value']);

        // Verify using mockery expectations
        $this->assertTrue(true, 'Info was not logged when disabled');
    }

    public function test_logs_debug_when_logging_enabled(): void
    {
        // Enable logging feature
        $this->filter->enableFeature('logging');

        // Expect a call to the logger's debug method
        $this->logger->shouldReceive('debug')
            ->once()
            ->with('Test debug message', ['key' => 'value']);

        // Call logDebug through reflection
        $method = new ReflectionMethod($this->filter, 'logDebug');
        $method->setAccessible(true);
        $method->invoke($this->filter, 'Test debug message', ['key' => 'value']);

        // Verify using mockery expectations
        $this->assertTrue(true, 'Debug was logged correctly');
    }

    public function test_does_not_log_debug_when_logging_disabled(): void
    {
        // Disable logging feature
        $this->filter->disableFeature('logging');

        // Expect no calls to the logger's debug method
        $this->logger->shouldNotReceive('debug');

        // Call logDebug through reflection
        $method = new ReflectionMethod($this->filter, 'logDebug');
        $method->setAccessible(true);
        $method->invoke($this->filter, 'Test debug message', ['key' => 'value']);

        // Verify using mockery expectations
        $this->assertTrue(true, 'Debug was not logged when disabled');
    }

    public function test_logs_warning_when_logging_enabled(): void
    {
        // Enable logging feature
        $this->filter->enableFeature('logging');

        // Expect a call to the logger's warning method
        $this->logger->shouldReceive('warning')
            ->once()
            ->with('Test warning message', ['key' => 'value']);

        // Call logWarning through reflection
        $method = new ReflectionMethod($this->filter, 'logWarning');
        $method->setAccessible(true);
        $method->invoke($this->filter, 'Test warning message', ['key' => 'value']);

        // Verify using mockery expectations
        $this->assertTrue(true, 'Warning was logged correctly');
    }

    public function test_does_not_log_warning_when_logging_disabled(): void
    {
        // Disable logging feature
        $this->filter->disableFeature('logging');

        // Expect no calls to the logger's warning method
        $this->logger->shouldNotReceive('warning');

        // Call logWarning through reflection
        $method = new ReflectionMethod($this->filter, 'logWarning');
        $method->setAccessible(true);
        $method->invoke($this->filter, 'Test warning message', ['key' => 'value']);

        // Verify using mockery expectations
        $this->assertTrue(true, 'Warning was not logged when disabled');
    }

    public function test_logging_feature_controls_multiple_log_methods(): void
    {
        // Start with logging enabled
        $this->filter->enableFeature('logging');

        // Expect calls to all log methods
        $this->logger->shouldReceive('info')->once()->with('Info message', []);
        $this->logger->shouldReceive('debug')->once()->with('Debug message', []);
        $this->logger->shouldReceive('warning')->once()->with('Warning message', []);

        // Get the reflection methods
        $infoMethod = new ReflectionMethod($this->filter, 'logInfo');
        $debugMethod = new ReflectionMethod($this->filter, 'logDebug');
        $warningMethod = new ReflectionMethod($this->filter, 'logWarning');

        $infoMethod->setAccessible(true);
        $debugMethod->setAccessible(true);
        $warningMethod->setAccessible(true);

        // Call all log methods
        $infoMethod->invoke($this->filter, 'Info message', []);
        $debugMethod->invoke($this->filter, 'Debug message', []);
        $warningMethod->invoke($this->filter, 'Warning message', []);

        // Now disable logging
        $this->filter->disableFeature('logging');

        // No more calls should happen
        $this->logger->shouldNotReceive('info');
        $this->logger->shouldNotReceive('debug');
        $this->logger->shouldNotReceive('warning');

        // Call all log methods again
        $infoMethod->invoke($this->filter, 'Info message 2', []);
        $debugMethod->invoke($this->filter, 'Debug message 2', []);
        $warningMethod->invoke($this->filter, 'Warning message 2', []);

        // Verify all mockery expectations
        $this->assertTrue(true, 'Logging feature controls all log methods');
    }

    public function test_logging_works_with_empty_context(): void
    {
        // Enable logging feature
        $this->filter->enableFeature('logging');

        // Expect a call to the logger's info method with empty context array
        $this->logger->shouldReceive('info')
            ->once()
            ->with('Message with empty context', []);

        // Call logInfo through reflection with no context
        $method = new ReflectionMethod($this->filter, 'logInfo');
        $method->setAccessible(true);
        $method->invoke($this->filter, 'Message with empty context');

        // Verify using mockery expectations
        $this->assertTrue(true, 'Logging works with empty context');
    }
}
