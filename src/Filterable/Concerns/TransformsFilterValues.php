<?php

namespace Filterable\Concerns;

trait TransformsFilterValues
{
    /**
     * Transformers for filter values.
     *
     * @var array<string, callable>
     */
    protected array $transformers = [];

    /**
     * Register a transformer for a filter.
     */
    public function registerTransformer(string $filter, callable $transformer): self
    {
        $this->transformers[$filter] = $transformer;

        return $this;
    }

    /**
     * Transform a filter value based on registered transformers.
     */
    protected function transformFilterValue(string $filter, mixed $value): mixed
    {
        if (isset($this->transformers[$filter])) {
            return call_user_func($this->transformers[$filter], $value);
        }

        return $value;
    }

    /**
     * Apply a transformer to each value in an array.
     */
    protected function transformArray(array $values, callable $transformer): array
    {
        return array_map($transformer, $values);
    }
}
