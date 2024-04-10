<?php

namespace Filterable\Interfaces;

use Exception;
use Illuminate\Database\Eloquent\Builder;

/**
 * Interface Filterable
 *
 * @package Filterable\Interfaces
 */
interface Filterable
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
    ): Builder;
}
