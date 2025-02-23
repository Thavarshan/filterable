<?php

namespace Filterable\Interfaces;

use Exception;
use Illuminate\Database\Eloquent\Builder;

/**
 * Interface Filterable
 */
interface Filterable
{
    /**
     * Apply all relevant space filters.
     *
     *
     * @return \Illuminate\Database\Eloquent\Builder $query
     *
     * @throws Exception
     */
    public function scopeFilter(
        Builder $query,
        Filter $filters,
        ?array $options = []
    ): Builder;
}
