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
