<?php

namespace Filterable\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class FilterRules
{
    public function __construct(public array|string $rules = [], public array $messages = [], public ?string $factory = null) {}
}
