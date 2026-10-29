<?php

namespace Filterable\Traits;

use Filterable\Contracts\Filter;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/** Optional Laravel 12+ alternative to the legacy Filterable trait. */
trait AttributeFilterable
{
    #[Scope]
    protected function filter(Builder $query, Filter $filter, ?array $options = []): Builder
    {
        return $filter->apply($query, $options);
    }
}
