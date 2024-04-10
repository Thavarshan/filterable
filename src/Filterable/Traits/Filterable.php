<?php

namespace Filterable\Traits;

use Exception;
use Filterable\Interfaces\Filter;
use Illuminate\Database\Eloquent\Builder;

trait Filterable
{
    /**
     * Apply all relevant space filters.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Filterable\Interfaces\Filter         $filters
     * @param array|null                            $options
     *
     * @return \Illuminate\Database\Eloquent\Builder $query
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
