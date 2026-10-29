<?php

namespace Filterable\Definitions;

final readonly class FilterDefinition
{
    public function __construct(public string $name, public string $method) {}
}
