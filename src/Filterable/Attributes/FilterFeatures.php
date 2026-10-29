<?php

namespace Filterable\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class FilterFeatures
{
    public function __construct(public array $features) {}
}
