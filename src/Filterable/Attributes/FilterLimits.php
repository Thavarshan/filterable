<?php

namespace Filterable\Attributes;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class FilterLimits
{
    public function __construct(public int $maxFilters = 10, public int $maxComplexity = 100)
    {
        if ($maxFilters < 0 || $maxComplexity < 0) {
            throw new InvalidArgumentException('FilterLimits must be zero or greater.');
        }
    }
}
