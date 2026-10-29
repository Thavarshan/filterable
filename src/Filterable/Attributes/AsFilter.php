<?php

namespace Filterable\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class AsFilter
{
    public function __construct(public string $name) {}
}
