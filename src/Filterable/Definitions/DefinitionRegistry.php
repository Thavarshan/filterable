<?php

namespace Filterable\Definitions;

use Filterable\Attributes\AsFilter;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;

final class DefinitionRegistry
{
    /** @var array<class-string, array<string, FilterDefinition>> */
    private static array $definitions = [];

    public static function clear(): void
    {
        self::$definitions = [];
    }

    /** Only immutable names are shared. Rules, transformers and services are resolved per instance. */
    public static function forClass(string $class): array
    {
        if (isset(self::$definitions[$class])) {
            return self::$definitions[$class];
        }

        $definitions = [];
        foreach ((new ReflectionClass($class))->getMethods() as $method) {
            foreach ($method->getAttributes(AsFilter::class) as $attribute) {
                $name = $attribute->newInstance()->name;
                self::validateMethod($method);
                if ($name === '' || isset($definitions[$name])) {
                    throw new InvalidArgumentException("Duplicate or empty filter alias [{$name}] on {$class}.");
                }
                $definitions[$name] = new FilterDefinition($name, $method->name);
            }
        }

        return self::$definitions[$class] = $definitions;
    }

    public static function validateMethod(ReflectionMethod $method): void
    {
        $parameters = $method->getParameters();
        if ($method->isStatic() || $method->isPrivate() || $method->isAbstract()
            || count($parameters) < 1 || $method->getNumberOfRequiredParameters() > 1
            || $parameters[0]->isPassedByReference() || $parameters[0]->isVariadic()) {
            throw new InvalidArgumentException("Filter handler [{$method->class}::{$method->name}] must be public/protected, non-static and accept one value.");
        }
    }
}
