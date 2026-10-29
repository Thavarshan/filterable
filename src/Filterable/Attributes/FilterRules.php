<?php

namespace Filterable\Attributes;

use Attribute;
use Filterable\Contracts\RuleFactory;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class FilterRules
{
    /**
     * @param  array<mixed>|string  $rules
     * @param  array<string, string>  $messages
     * @param  class-string<RuleFactory>|null  $factory
     */
    public function __construct(public array|string $rules = [], public array $messages = [], public ?string $factory = null) {}
}
