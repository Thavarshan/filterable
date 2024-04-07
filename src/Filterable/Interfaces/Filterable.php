<?php

namespace Filterable\Interfaces;

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
     * @param \Filterable\Interfaces\Filter $filters
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function filter(Filter $filters): Builder;
}
