<?php

namespace Filterable\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class UseFilter
{
    /** @param class-string $filter */
    public function __construct(public string $filter) {}
}
