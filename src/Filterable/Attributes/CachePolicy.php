<?php

namespace Filterable\Attributes;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class CachePolicy
{
    public function __construct(
        public int $ttlMinutes = 5,
        public bool $results = true,
        public bool $count = false,
        public array $tags = [],
    ) {
        if ($ttlMinutes < 0) {
            throw new InvalidArgumentException('CachePolicy TTL must be zero or greater.');
        }
    }
}
