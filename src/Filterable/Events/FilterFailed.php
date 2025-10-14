<?php

namespace Filterable\Events;

use Filterable\Filter;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class FilterFailed
{
    public function __construct(
        public readonly Filter $filter,
        public readonly Builder $builder,
        public readonly Throwable $exception
    ) {}
}
