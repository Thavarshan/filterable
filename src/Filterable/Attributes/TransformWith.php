<?php

namespace Filterable\Attributes;

use Attribute;
use Closure;
use InvalidArgumentException;
use ReflectionFunction;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class TransformWith
{
    /** @param string|array{class-string, string}|Closure $transformer */
    public function __construct(public string|array|Closure $transformer)
    {
        if ($transformer instanceof Closure && (new ReflectionFunction($transformer))->getClosureThis() !== null) {
            throw new InvalidArgumentException('Attribute transformers must not bind an instance.');
        }
    }
}
