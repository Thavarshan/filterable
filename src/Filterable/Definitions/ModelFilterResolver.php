<?php

namespace Filterable\Definitions;

use BadMethodCallException;
use Closure;
use Filterable\Attributes\UseFilter;
use Filterable\Contracts\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use ReflectionClass;

final class ModelFilterResolver
{
    public static function macro(): Closure
    {
        return Closure::bind(
            function (mixed ...$parameters): mixed {
                /** @var Builder<Model> $this */
                if ($this->hasNamedScope('filter')) {
                    return $this->callNamedScope('filter', $parameters);
                }

                $this->callScope(function (Builder $query) use ($parameters): void {
                    // Dynamic dispatch preserves positional and named macro arguments.
                    ModelFilterResolver::apply($query, ...$parameters);
                });

                return $this;
            },
            null,
            Builder::class,
        );
    }

    /**
     * @param Builder<*> $builder
     */
    public static function apply(Builder $builder, mixed $filter = null, mixed $options = []): void
    {
        if ($filter !== null && ! $filter instanceof Filter) {
            throw new InvalidArgumentException('filter() expects a Filter implementation or null.');
        }
        if ($options !== null && ! is_array($options)) {
            throw new InvalidArgumentException('filter() options must be an array or null.');
        }
        /** @var array<string, mixed>|null $options */

        // Require an attributed model even when supplying an override.
        $class = self::filterClass($builder->getModel());
        $filter ??= app($class);
        if (! $filter instanceof Filter) {
            throw new InvalidArgumentException('The container must resolve a Filter implementation for '.$class.'.');
        }
        $filter->apply($builder, $options);
    }

    /**
     * Resolve metadata only; never retain a mutable filter instance.
     *
     * @return class-string<Filter>
     */
    public static function filterClass(Model $model): string
    {
        $reflection = new ReflectionClass($model);
        do {
            $attributes = $reflection->getAttributes(UseFilter::class);
            if ($attributes !== []) {
                $class = $attributes[0]->newInstance()->filter;
                if (! is_a($class, Filter::class, true)) {
                    throw new InvalidArgumentException('UseFilter must reference a class implementing '.Filter::class.'.');
                }

                return $class;
            }
        } while ($reflection = $reflection->getParentClass());

        throw new BadMethodCallException('Model '.$model::class.' must declare #[UseFilter] or a filter scope.');
    }
}
