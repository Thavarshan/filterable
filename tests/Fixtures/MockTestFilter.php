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
    /**
     * Apply the filter to a builder instance.
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

        return $this->builder;
    }

    /**
     * Get the query results with proper handling based on enabled features.
     */
    public function get(): Collection
    {
        if ($this->state !== 'applied') {
            throw new RuntimeException('You must call apply() before get()');
        }

        if ($this->state === 'failed') {
            throw new RuntimeException('Filters failed to apply: '.$this->failureReason);
        }

        if ($this->hasFeature('memoryManagement')) {
            return $this->mockExecuteQueryWithMemoryManagement();
        }

        return $this->builder->get();
    }

    /**
     * Public method that exposes the protected executeQueryWithMemoryManagement method
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
