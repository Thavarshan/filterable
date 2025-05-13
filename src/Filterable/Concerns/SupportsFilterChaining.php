<?php

namespace Filterable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;

trait SupportsFilterChaining
{
    /**
     * Custom filters to be applied.
     *
     * @var array<Closure>
     */
    protected array $customFilters = [];

    /**
     * Add a custom where clause to the query.
     */
    public function where(string $column, mixed $operator = null, mixed $value = null): self
    {
        // Handle different parameter formats
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->customFilters[] = function (Builder $query) use ($column, $operator, $value) {
            return $query->where($column, $operator, $value);
        };

        return $this;
    }

    /**
     * Add a custom where in clause to the query.
     */
    public function whereIn(string $column, array $values): self
    {
        $this->customFilters[] = function (Builder $query) use ($column, $values) {
            return $query->whereIn($column, $values);
        };

        return $this;
    }

    /**
     * Add a custom where not in clause to the query.
     */
    public function whereNotIn(string $column, array $values): self
    {
        $this->customFilters[] = function (Builder $query) use ($column, $values) {
            return $query->whereNotIn($column, $values);
        };

        return $this;
    }

    /**
     * Add a custom where between clause to the query.
     */
    public function whereBetween(string $column, array $values): self
    {
        $this->customFilters[] = function (Builder $query) use ($column, $values) {
            return $query->whereBetween($column, $values);
        };

        return $this;
    }

    /**
     * Add a custom order by clause to the query.
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->customFilters[] = function (Builder $query) use ($column, $direction) {
            return $query->orderBy($column, $direction);
        };

        return $this;
    }

    /**
     * Apply all custom filters to the query.
     */
    protected function applyCustomFilters(): void
    {
        foreach ($this->customFilters as $filter) {
            $filter($this->builder);
        }
    }
}
