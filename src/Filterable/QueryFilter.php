<?php

namespace Filterable;

use Filterable\Contracts\Filter;
use Illuminate\Database\Eloquent\Builder;

/** Use with Eloquent tap() to mutate or pipe() to return a filtered builder. */
final readonly class QueryFilter
{
    public function __construct(private Filter $filter, private array $options = []) {}

    public function __invoke(Builder $builder): Builder
    {
        return $this->filter->apply($builder, $this->options);
    }
}
