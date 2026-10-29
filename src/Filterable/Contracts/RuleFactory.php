<?php

namespace Filterable\Contracts;

use Illuminate\Http\Request;

interface RuleFactory
{
    /** @return array<mixed>|string */
    public function __invoke(Request $request): array|string;
}
