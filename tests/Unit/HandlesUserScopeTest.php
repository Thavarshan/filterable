<?php

namespace Filterable\Tests\Unit;

use Filterable\Tests\Fixtures\TestFilter;
use Filterable\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Mockery as m;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use ReflectionProperty;

class HandlesUserScopeTest extends TestCase
{
    protected $request;

    protected $builder;

    protected $filter;

    protected $user;

    protected $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new Request;

        // Create a mock builder
        $this->builder = m::mock(Builder::class);

        // Create a mock user
        $this->user = $this->createMockUser();

        // Create a mock logger
        $this->logger = m::mock(LoggerInterface::class);

        // Create a filter
        $this->filter = new TestFilter($this->request, null, $this->logger);
        $this->filter->setBuilder($this->builder);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_can_set_user_for_filtering(): void
    {
        // Set the user
        $result = $this->filter->forUser($this->user);

        // Check fluent interface
        $this->assertSame($this->filter, $result);

        // Verify user was set correctly using reflection
        $reflectionProperty = new ReflectionProperty($this->filter, 'forUser');
        $reflectionProperty->setAccessible(true);
        $this->assertSame($this->user, $reflectionProperty->getValue($this->filter));
    }

    public function test_can_set_null_user_for_filtering(): void
    {
        // Set a user first
        $this->filter->forUser($this->user);

        // Then set null
        $result = $this->filter->forUser(null);

        // Check fluent interface
        $this->assertSame($this->filter, $result);

        // Verify user was set to null
        $reflectionProperty = new ReflectionProperty($this->filter, 'forUser');
        $reflectionProperty->setAccessible(true);
        $this->assertNull($reflectionProperty->getValue($this->filter));
    }

    public function test_does_not_apply_user_scope_when_no_user_is_set(): void
    {
        // Don't set a user

        // The builder should not receive a where call
        $this->builder->shouldNotReceive('where');

        // Call the method
        $reflectionMethod = new ReflectionMethod($this->filter, 'applyUserScope');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($this->filter);

        $this->assertTrue(true, 'User scope was not applied when no user is set');
    }

    public function test_applies_user_scope_when_user_is_set(): void
    {
        // Set the user
        $this->filter->forUser($this->user);
        $this->filter->disableFeature('logging');

        // The builder should receive a where call with the user's ID
        $this->builder->shouldReceive('where')
            ->once()
            ->with('id', 123)
            ->andReturnSelf();

        // Call the method
        $reflectionMethod = new ReflectionMethod($this->filter, 'applyUserScope');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($this->filter);

        $this->assertTrue(true, 'User scope was applied correctly');
    }

    public function test_logs_user_scope_application_when_logging_enabled(): void
    {
        // Set the user
        $this->filter->forUser($this->user);

        // Enable logging
        $this->filter->enableFeature('logging');

        // Expect a call to the logger
        $this->logger->shouldReceive('info')
            ->once()
            ->with('Applying user-specific filter', [
                'attribute' => 'id',
                'value' => 123,
            ]);

        // The builder should receive a where call
        $this->builder->shouldReceive('where')
            ->once()
            ->with('id', 123)
            ->andReturnSelf();

        // Call the method
        $reflectionMethod = new ReflectionMethod($this->filter, 'applyUserScope');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($this->filter);

        $this->assertTrue(true, 'User scope was logged correctly');
    }

    public function test_does_not_log_when_logging_disabled(): void
    {
        // Set the user
        $this->filter->forUser($this->user);

        // Ensure logging is disabled
        $this->filter->disableFeature('logging');

        // Logger should not receive an info call
        $this->logger->shouldNotReceive('info');

        // The builder should still receive a where call
        $this->builder->shouldReceive('where')
            ->once()
            ->with('id', 123)
            ->andReturnSelf();

        // Call the method
        $reflectionMethod = new ReflectionMethod($this->filter, 'applyUserScope');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($this->filter);

        $this->assertTrue(true, 'User scope was applied without logging');
    }

    public function test_user_scope_is_applied_during_filter_application(): void
    {
        // Create a new filter with a mock builder
        $builder = m::mock(Builder::class);
        $filter = new TestFilter($this->request);

        // Set the user
        $filter->forUser($this->user);

        // The builder should receive a where call for the user
        $builder->shouldReceive('where')
            ->once()
            ->with('id', 123)
            ->andReturnSelf();

        // Other builder expectations for a complete filter application
        $builder->shouldReceive('get')
            ->andReturn(collect());

        // Apply the filter
        $filter->apply($builder);

        $this->assertTrue(true, 'User scope was applied during filter application');
    }

    public function test_handles_different_user_identifier_fields(): void
    {
        // Create a user with a different identifier field
        $user = m::mock(Authenticatable::class);
        $user->shouldReceive('getAuthIdentifierName')
            ->andReturn('uuid');
        $user->shouldReceive('getAuthIdentifier')
            ->andReturn('abc-123-xyz');

        // Set the user
        $this->filter->forUser($user);
        $this->filter->disableFeature('logging');

        // The builder should receive a where call with the custom identifier
        $this->builder->shouldReceive('where')
            ->once()
            ->with('uuid', 'abc-123-xyz')
            ->andReturnSelf();

        // Call the method
        $reflectionMethod = new ReflectionMethod($this->filter, 'applyUserScope');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($this->filter);

        $this->assertTrue(true, 'User scope was applied with a different identifier field');
    }

    /**
     * Create a mock user for testing.
     */
    protected function createMockUser(): Authenticatable
    {
        $user = m::mock(Authenticatable::class);
        $user->shouldReceive('getAuthIdentifierName')
            ->andReturn('id');
        $user->shouldReceive('getAuthIdentifier')
            ->andReturn(123);

        return $user;
    }
}
