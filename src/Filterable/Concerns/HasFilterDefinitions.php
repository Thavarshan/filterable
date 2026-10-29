<?php

namespace Filterable\Concerns;

use Filterable\Attributes\CachePolicy;
use Filterable\Attributes\FilterComplexity;
use Filterable\Attributes\FilterFeatures;
use Filterable\Attributes\FilterLimits;
use Filterable\Attributes\FilterRules;
use Filterable\Attributes\RequiresPermission;
use Filterable\Attributes\TransformWith;
use Filterable\Definitions\DefinitionRegistry;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;

trait HasFilterDefinitions
{
    protected array $permissionPolicies = [];

    protected function applyDefinitionDefaults(): void
    {
        $featureOverrides = [];
        $classes = [];
        $class = new ReflectionClass($this);
        do {
            $classes[] = $class;
        } while ($class = $class->getParentClass());

        foreach (array_reverse($classes) as $class) {
            foreach ([CachePolicy::class, FilterLimits::class, FilterFeatures::class] as $type) {
                foreach ($class->getAttributes($type) as $attribute) {
                    $defaults = $attribute->newInstance();
                    if ($defaults instanceof CachePolicy) {
                        $this->cacheExpiration = $defaults->ttlMinutes;
                        $this->shouldCacheResults = $defaults->results;
                        $this->shouldCacheCount = $defaults->count;
                        $this->cacheTags = $defaults->tags;
                        $this->features['caching'] = true;
                    } elseif ($defaults instanceof FilterLimits) {
                        $this->maxFilters = $defaults->maxFilters;
                        $this->maxComplexity = $defaults->maxComplexity;
                        $this->features['rateLimit'] = true;
                    } else {
                        foreach ($defaults->features as $feature => $enabled) {
                            if (! array_key_exists($feature, $this->features) || ! is_bool($enabled)) {
                                throw new InvalidArgumentException("Invalid FilterFeatures entry [{$feature}].");
                            }
                            $this->features[$feature] = $enabled;
                            $featureOverrides[$feature] = $enabled;
                        }
                    }
                }
            }
        }

        foreach (DefinitionRegistry::forClass(static::class) as $name => $definition) {
            $legacy = $this->filterMethodMap[$name] ?? (in_array($name, $this->filters, true) ? \Illuminate\Support\Str::camel($name) : null);
            if ($legacy !== null && $legacy !== $definition->method) {
                throw new InvalidArgumentException("Conflicting legacy and attribute handlers for [{$name}].");
            }
            $this->filterMethodMap[$name] = $definition->method;
            $method = new ReflectionMethod($this, $definition->method);
            foreach ([FilterRules::class, TransformWith::class, RequiresPermission::class, FilterComplexity::class] as $type) {
                foreach ($method->getAttributes($type) as $attribute) {
                    $metadata = $attribute->newInstance();
                    if ($metadata instanceof FilterRules) {
                        $this->validationRules[$name] = $metadata->factory === null ? $metadata->rules : app($metadata->factory)($this->request);
                        $this->validationMessages = array_replace($this->validationMessages, $metadata->messages);
                        $this->features['validation'] = true;
                    } elseif ($metadata instanceof TransformWith) {
                        $transformer = $metadata->transformer;
                        $this->transformers[$name] = is_string($transformer) && class_exists($transformer) ? app($transformer) : $transformer;
                        if (! is_callable($this->transformers[$name])) {
                            throw new InvalidArgumentException("Invalid transformer for [{$name}].");
                        }
                        $this->features['valueTransformation'] = true;
                    } elseif ($metadata instanceof RequiresPermission) {
                        $this->filterPermissions[$name] = $metadata->permissions;
                        $this->permissionPolicies[$name] = $metadata;
                        $this->features['permissions'] = true;
                    } else {
                        $this->filterComplexity[$name] = $metadata->weight;
                        $this->features['rateLimit'] = true;
                    }
                }
            }
        }
        $this->features = array_replace($this->features, $featureOverrides);
    }

    protected function validateFilterDefinitions(): void
    {
        foreach (array_unique(array_merge($this->filters, array_keys($this->filterMethodMap))) as $name) {
            $method = $this->makeFilterIntoMethodName($name);
            if (! method_exists($this, $method)) {
                throw new InvalidArgumentException("Missing registered filter handler [{$method}].");
            }
            DefinitionRegistry::validateMethod(new ReflectionMethod($this, $method));
        }
        foreach (array_keys($this->filterables) as $name) {
            if (! in_array($name, $this->filters, true) && ! array_key_exists($name, $this->filterMethodMap)) {
                throw new InvalidArgumentException("Unregistered filter [{$name}].");
            }
        }
    }
}
