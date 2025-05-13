<?php

namespace Filterable\Concerns;

use BadMethodCallException;
use Closure;
use Filterable\Contracts\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait HandlesFilterables
{
    /**
     * The parent filter instance.
     */
    protected Filter $filter;

    /**
     * Registered filters to operate upon.
     *
     * @var array<string>
     */
    protected array $filters = [];

    /**
     * Map of filter names to methods.
     *
     * @var array<string, string>
     */
    protected array $filterMethodMap = [];

    /**
     * All filters that have been chosen to be applied.
     *
     * @var array<string, mixed>
     */
    protected array $filterables = [];

    /**
     * The current filters being applied.
     *
     * @var array<string>
     */
    protected array $currentFilters = [];

    /**
     * Initialize with the filter instance.
     */
    public function initialize(Filter $filter): void
    {
        $this->filter = $filter;

        // Copy the filters array from the parent filter
        if (property_exists($filter, 'filters') && is_array($filter->filters)) {
            $this->filters = $filter->filters;
        }

        // Copy the filter method map if it exists
        if (property_exists($filter, 'filterMethodMap') && is_array($filter->filterMethodMap)) {
            $this->filterMethodMap = $filter->filterMethodMap;
        }
    }

    /**
     * Apply the filterables to the query.
     */
    protected function applyFilterables(): void
    {
        if (method_exists($this, 'shouldCache') && $this->shouldCache()) {
            $this->applyFilterablesWithCache();

            return;
        }

        $this->applyFiltersToQuery();
    }

    /**
     * Execute the query builder query functionality with the filters applied.
     */
    protected function applyFiltersToQuery(): void
    {
        collect($this->getFilterables())
            ->filter(fn (mixed $value) => $value !== null
                && $value !== ''
                && $value !== false
                && $value !== [])
            ->each(function ($value, $filter) {
                $this->applyFilterable($filter, $value);
            });
    }

    /**
     * Apply a filter to the query.
     */
    protected function applyFilterable(string $filter, mixed $value): void
    {
        $method = $this->makeFilterIntoMethodName($filter);

        if (! method_exists($this, $method)) {
            throw new BadMethodCallException(
                sprintf('Method [%s] does not exist on %s', $method, static::class)
            );
        }

        if (method_exists($this, 'logInfo')) {
            $this->logInfo("Applying filter method: {$method}", [
                'filter' => $filter,
                'value' => $value,
            ]);
        }

        call_user_func([$this, $method], $value);
    }

    /**
     * Make the filter into a method name.
     */
    protected function makeFilterIntoMethodName(string $filter): string
    {
        return $this->filterMethodMap[$filter] ?? Str::camel($filter);
    }

    /**
     * Fetch all relevant filters (key, value) from the request.
     *
     * @return array<string, mixed>
     */
    public function getFilterables(): array
    {
        $filterKeys = array_merge(
            $this->getFilters(),
            array_keys($this->filterMethodMap ?? [])
        );

        // Contains key, value pairs of the filters
        $this->filterables = array_merge(
            $this->filterables,
            array_filter($this->request->only($filterKeys))
        );

        $this->currentFilters = array_keys($this->filterables);

        return $this->filterables;
    }

    /**
     * Get the registered filters.
     *
     * @return array<string>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Append a filterable value to the filter.
     */
    public function appendFilterable(string $key, mixed $value): Filter
    {
        $this->filterables[$key] = $value;

        return $this;
    }

    /**
     * Get the current filters being applied.
     *
     * @return array<string>
     */
    public function getCurrentFilters(): array
    {
        return $this->currentFilters;
    }

    /**
     * Apply all relevant filters to the query and present it
     * as a callable for use within a collection instance.
     *
     * @see https://laravel.com/docs/10.x/collections#method-filter
     */
    public function asCollectionFilter(): Closure
    {
        return fn (mixed $items) => collect($this->getFilterables());
    }
}
