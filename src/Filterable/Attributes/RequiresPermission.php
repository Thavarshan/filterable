<?php

namespace Filterable\Attributes;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class RequiresPermission
{
    /** @param string|list<string> $permissions */
    public function __construct(public string|array $permissions, public bool $any = false, public bool $reject = false)
    {
        if ((array) $permissions === []) {
            throw new InvalidArgumentException('RequiresPermission needs non-empty abilities.');
        }
        foreach ((array) $permissions as $permission) {
            if (! is_string($permission) || $permission === '') {
                throw new InvalidArgumentException('Permission abilities must be non-empty strings.');
            }
        }
    }
}
