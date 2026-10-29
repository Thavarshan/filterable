<?php

namespace Filterable\Contracts;

use Exception;
use Illuminate\Database\Eloquent\Builder;

interface Filterable
{
    /**
     * Apply all relevant space filters.
     *
     *
     * @param \Illuminate\Database\Eloquent\Builder<*> $query
     * @param  array<string, mixed>|null  $options
     * @return Builder<*> $query
     *
     * @throws Exception
     */
    public function scopeFilter(
        Builder $query,
        Filter $filters,
        ?array $options = []
    ): Builder;
}
