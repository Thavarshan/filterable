<?php

namespace Filterable\Events;

use Filterable\Filter;
use Illuminate\Database\Eloquent\Builder;

class FilterApplied
{
    public function __construct(
        public readonly Filter $filter,
        public readonly Builder $builder,
        /**
         * @var array<string, mixed>
         */
        public readonly array $filters
    ) {}
}
