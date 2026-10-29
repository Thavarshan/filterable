<?php

namespace Filterable;

use Filterable\Contracts\Filter;
use Illuminate\Database\Eloquent\Builder;

/** Use with Eloquent tap() to mutate or pipe() to return a filtered builder. */
final readonly class QueryFilter
{
    /** @param array<string, mixed> $options */
    public function __construct(private Filter $filter, private array $options = []) {}

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $builder
     * @return Builder<TModel>
     */
    public function __invoke(Builder $builder): Builder
    {
        $this->filter->apply($builder, $this->options);

        return $builder;
    }
}
