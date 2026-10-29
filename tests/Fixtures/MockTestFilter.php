<?php

namespace Filterable\Tests\Fixtures;

use Exception;
use Filterable\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MockTestFilter extends Filter
{
    protected ?string $failureReason = null;

    /**
     * Apply the filter to a builder instance.
     *
     * @param \Illuminate\Database\Eloquent\Builder<*> $builder
     * @param  array<string, mixed>|null  $options
     * @return \Illuminate\Database\Eloquent\Builder<*>
     */
    public function apply(Builder $builder, ?array $options = []): Builder
    {
        $this->builder = $builder;
        $this->options = array_merge($this->options, $options ?? []);
        $this->state = 'applying';

        try {
            // Run the regular filter methods
            $this->applyUserScope();
            $this->applyPreFilters();
            $this->applyFilterables();

            // Set state to applied
            $this->state = 'applied';
        } catch (Exception $e) {
            $this->state = 'failed';
            $this->failureReason = $e->getMessage();

            if ($e instanceof ValidationException) {
                throw $e;
            }
        }

        return $this->resolveBuilder();
    }

    /**
     * Get the query results with proper handling based on enabled features.
     *
     * @return \Illuminate\Database\Eloquent\Collection<*, *>
     */
    public function get(): Collection
    {
        if ($this->state === 'failed') {
            throw new RuntimeException('Filters failed to apply: '.$this->failureReason);
        }

        if ($this->state !== 'applied') {
            throw new RuntimeException('You must call apply() before get()');
        }

        if ($this->hasFeature('memoryManagement')) {
            return $this->mockExecuteQueryWithMemoryManagement();
        }

        return $this->resolveBuilder()->get();
    }

    /**
     * Public method that exposes the protected executeQueryWithMemoryManagement method
     *
     * @return \Illuminate\Database\Eloquent\Collection<*, *>
     */
    public function mockExecuteQueryWithMemoryManagement(): Collection
    {
        // In a real implementation, this would call the protected method
        // For testing, we'll return a mock result
        return new Collection;
    }

    /**
     * Overridden method that would be called during apply()
     */
    protected function applyUserScope(): void
    {
        // Implementation for testing
    }

    /**
     * Overridden method that would be called during apply()
     */
    protected function applyPreFilters(): void
    {
        // Implementation for testing
    }

    /**
     * Overridden method that would be called during apply()
     */
    protected function applyFilterables(): void
    {
        // Implementation for testing
    }

    /**
     * Overridden method for validation
     */
    protected function validateFilterInputs(): void
    {
        // This would be called if validation feature is enabled
    }

    /**
     * Overridden method for timing
     */
    protected function startTiming(): void
    {
        // This would be called if performance feature is enabled
    }

    /**
     * Overridden method for timing
     */
    protected function endTiming(): void
    {
        // This would be called if performance feature is enabled
    }

    /**
     * Overridden method for transformation
     */
    protected function transformFilterValues(): void
    {
        // This would be called if valueTransformation feature is enabled
    }
}
