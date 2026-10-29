<?php

namespace Filterable\Traits;

use Filterable\Contracts\Filter;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/** Optional Laravel 12+ alternative to the legacy Filterable trait. */
trait AttributeFilterable
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>|null  $options
     * @return Builder<TModel>
     */
    #[Scope]
    protected function filter(Builder $query, Filter $filter, ?array $options = []): Builder
    {
        $filter->apply($query, $options);

        return $query;
    }
}
