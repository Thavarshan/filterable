<?php

namespace Filterable\Traits;

use Exception;
use Filterable\Contracts\Filter;
use Illuminate\Database\Eloquent\Builder;

trait Filterable
{
    /**
     * Apply all relevant space filters.
     *
     * @param \Illuminate\Database\Eloquent\Builder<*> $query
     * @param  array<string, mixed>|null  $options
     * @return \Illuminate\Database\Eloquent\Builder<*>
     *
     * @throws Exception
     */
    public function scopeFilter(
        Builder $query,
        Filter $filters,
        ?array $options = []
    ): Builder {
        return $filters->apply($query, $options);
    }
}
