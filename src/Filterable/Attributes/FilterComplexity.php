<?php

namespace Filterable\Attributes;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class FilterComplexity
{
    public function __construct(public int $weight)
    {
        if ($weight < 0) {
            throw new InvalidArgumentException('FilterComplexity must be zero or greater.');
        }
    }
}
