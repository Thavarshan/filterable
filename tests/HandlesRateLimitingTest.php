<?php

namespace Filterable\Tests\Concerns;

use Filterable\Tests\Fixtures\TestFilter;
use Filterable\Tests\TestCase;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Mockery as m;
use ReflectionMethod;
use ReflectionProperty;

class HandlesRateLimitingTest extends TestCase
{
    protected $request;

    protected $filter;

    protected $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new Request;
        $this->filter = new TestFilter($this->request);
        $this->filter->enableFeature('rateLimit');

        // Mock the RateLimiter
        $this->rateLimiter = m::mock(RateLimiter::class);
        App::instance(RateLimiter::class, $this->rateLimiter);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_can_set_max_filters(): void
    {
        $result = $this->filter->setMaxFilters(5);

        // Test fluent interface
        $this->assertSame($this->filter, $result);

        // Verify value was set correctly using reflection
        $reflectionProperty = new ReflectionProperty($this->filter, 'maxFilters');
        $reflectionProperty->setAccessible(true);
        $this->assertEquals(5, $reflectionProperty->getValue($this->filter));
    }

    public function test_can_set_max_complexity(): void
    {
        $result = $this->filter->setMaxComplexity(50);

        // Test fluent interface
        $this->assertSame($this->filter, $result);

        // Verify value was set correctly
        $reflectionProperty = new ReflectionProperty($this->filter, 'maxComplexity');
        $reflectionProperty->setAccessible(true);
        $this->assertEquals(50, $reflectionProperty->getValue($this->filter));
    }

    public function test_can_set_filter_complexity(): void
    {
        $complexityMap = [
            'search' => 5,
            'sort' => 2,
            'date_range' => 10,
        ];

        $result = $this->filter->setFilterComplexity($complexityMap);

        // Test fluent interface
        $this->assertSame($this->filter, $result);

        // Verify value was set correctly
        $reflectionProperty = new ReflectionProperty($this->filter, 'filterComplexity');
        $reflectionProperty->setAccessible(true);
        $this->assertEquals($complexityMap, $reflectionProperty->getValue($this->filter));
    }

    public function test_calculate_complexity_gives_base_score_for_simple_filters(): void
    {
        // Set up test data
        $filter = $this->filter;
        $filter->appendFilterable('name', 'test');
        $filter->appendFilterable('status', 'active');

        // Use reflection to access protected method
        $reflectionMethod = new ReflectionMethod($filter, 'calculateComplexity');
        $reflectionMethod->setAccessible(true);

        // Calculate complexity
        $complexity = $reflectionMethod->invoke($filter);

        // Each filter adds 1 to complexity by default
        $this->assertEquals(2, $complexity);
    }

    public function test_calculate_complexity_uses_custom_score_for_configured_filters(): void
    {
        // Set up test data
        $filter = $this->filter;
        $filter->setFilterComplexity([
            'search' => 5,
            'date_range' => 10,
        ]);

        $filter->appendFilterable('search', 'test');
        $filter->appendFilterable('date_range', '2023-01-01,2023-12-31');
        $filter->appendFilterable('status', 'active'); // Default complexity (1)

        // Use reflection to access protected method
        $reflectionMethod = new ReflectionMethod($filter, 'calculateComplexity');
        $reflectionMethod->setAccessible(true);

        // Calculate complexity
        $complexity = $reflectionMethod->invoke($filter);

        // search (5) + date_range (10) + status (1) = 16
        $this->assertEquals(16, $complexity);
    }

    public function test_calculate_complexity_increases_for_array_values(): void
    {
        // Set up test data
        $filter = $this->filter;
        $filter->setFilterComplexity([
            'tags' => 3,
        ]);

        $filter->appendFilterable('tags', ['tag1', 'tag2', 'tag3']);

        // Use reflection to access protected method
        $reflectionMethod = new ReflectionMethod($filter, 'calculateComplexity');
        $reflectionMethod->setAccessible(true);

        // Calculate complexity
        $complexity = $reflectionMethod->invoke($filter);

        // tags has complexity 3 × array length 3 = 9
        $this->assertEquals(9, $complexity);
    }

    public function test_check_rate_limits_fails_when_too_many_filters(): void
    {
        // Set a low max filters value
        $filter = $this->filter;
        $filter->setMaxFilters(2);

        // Add more filters than allowed
        $filter->appendFilterable('filter1', 'value1');
        $filter->appendFilterable('filter2', 'value2');
        $filter->appendFilterable('filter3', 'value3');

        // Use reflection to access protected method
        $reflectionMethod = new ReflectionMethod($filter, 'checkRateLimits');
        $reflectionMethod->setAccessible(true);

        // Check rate limits
        $result = $reflectionMethod->invoke($filter);

        // Should fail because we have 3 filters but max is 2
        $this->assertFalse($result);
    }

    public function test_check_rate_limits_fails_when_too_complex(): void
    {
        // Set a low max complexity value
        $filter = $this->filter;
        $filter->setMaxComplexity(5);

        // Add filters with combined complexity > 5
        $filter->setFilterComplexity([
            'search' => 3,
            'date_range' => 4,
        ]);

        $filter->appendFilterable('search', 'test');
        $filter->appendFilterable('date_range', '2023-01-01,2023-12-31');

        // Use reflection to access protected method
        $reflectionMethod = new ReflectionMethod($filter, 'checkRateLimits');
        $reflectionMethod->setAccessible(true);

        // Check rate limits
        $result = $reflectionMethod->invoke($filter);

        // Should fail because complexity is 7 but max is 5
        $this->assertFalse($result);
    }

    public function test_check_rate_limits_uses_rate_limiter_for_throttling(): void
    {
        $filter = $this->filter;
        $ip = '127.0.0.1';

        // Mock the request IP
        $requestMock = m::mock(Request::class);
        $requestMock->shouldReceive('ip')
            ->andReturn($ip);
        $requestMock->shouldReceive('only')
            ->with(m::type('array'))
            ->andReturn([]);

        // Update filter to use our mocked request
        $reflectionProperty = new ReflectionProperty($filter, 'request');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($filter, $requestMock);

        // Add some filters
        $filter->appendFilterable('search', 'test');
        $filter->setFilterComplexity(['search' => 5]);

        // Expected rate limiter key
        $expectedKey = 'filter:'.md5($ip.'|'.get_class($filter));

        // Mock the rate limiter responses
        $this->rateLimiter->shouldReceive('tooManyAttempts')
            ->once()
            ->with($expectedKey, 60)
            ->andReturn(false);

        $this->rateLimiter->shouldReceive('hit')
            ->once()
            ->with($expectedKey, 1) // ceil(5/10) = 1
            ->andReturn(1);

        // Use reflection to access protected method
        $reflectionMethod = new ReflectionMethod($filter, 'checkRateLimits');
        $reflectionMethod->setAccessible(true);

        // Check rate limits
        $result = $reflectionMethod->invoke($filter);

        // Should succeed because tooManyAttempts returns false
        $this->assertTrue($result);
    }

    public function test_check_rate_limits_fails_when_rate_limited(): void
    {
        $filter = $this->filter;
        $ip = '127.0.0.1';

        // Mock the request IP
        $requestMock = m::mock(Request::class);
        $requestMock->shouldReceive('ip')
            ->andReturn($ip);
        $requestMock->shouldReceive('only')
            ->with(m::type('array'))
            ->andReturn([]);

        // Update filter to use our mocked request
        $reflectionProperty = new ReflectionProperty($filter, 'request');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($filter, $requestMock);

        // Add some filters
        $filter->appendFilterable('search', 'test');

        // Expected rate limiter key
        $expectedKey = 'filter:'.md5($ip.'|'.get_class($filter));

        // Mock the rate limiter to say too many attempts
        $this->rateLimiter->shouldReceive('tooManyAttempts')
            ->once()
            ->with($expectedKey, 60)
            ->andReturn(true);

        // The hit method should not be called if too many attempts
        $this->rateLimiter->shouldNotReceive('hit');

        // Use reflection to access protected method
        $reflectionMethod = new ReflectionMethod($filter, 'checkRateLimits');
        $reflectionMethod->setAccessible(true);

        // Check rate limits
        $result = $reflectionMethod->invoke($filter);

        // Should fail because tooManyAttempts returns true
        $this->assertFalse($result);
    }

    public function test_applies_rate_limiting_during_filter_application(): void
    {
        $filter = new class($this->request) extends TestFilter
        {
            // Override to expose the result
            public $rateLimitResult = null;

            protected function checkRateLimits(): bool
            {
                $this->rateLimitResult = parent::checkRateLimits();

                return $this->rateLimitResult;
            }
        };

        // Enable rate limiting
        $filter->enableFeature('rateLimit');

        // Mock the rateLimiter for the filter application
        $this->rateLimiter->shouldReceive('tooManyAttempts')
            ->once()
            ->andReturn(false);

        $this->rateLimiter->shouldReceive('hit')
            ->once()
            ->andReturn(1);

        // Create a mock builder
        $builderMock = m::mock(Builder::class);

        // Apply filter
        $filter->apply($builderMock);

        // Verify that checkRateLimits was called and returned true
        $this->assertTrue($filter->rateLimitResult);
    }
}
