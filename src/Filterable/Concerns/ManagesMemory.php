<?php

namespace Filterable\Concerns;

use Closure;
use Generator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

trait ManagesMemory
{
    /**
     * Use lazy evaluation of collections to reduce memory usage.
     */
    protected bool $useLazyCollection = false;

    /**
     * Execute the query and return a lazy collection.
     */
    public function lazy(int $chunkSize = 1000): LazyCollection
    {
        $this->useLazyCollection = true;
        $this->options['chunk_size'] = $chunkSize;

        // Apply all filters to the builder if not already applied
        if ($this->state !== 'applied') {
            $this->apply($this->getBuilder(), $this->options);
        }

        // Return a lazy collection that loads records in chunks
        return $this->getBuilder()->lazy($chunkSize);
    }

    /**
     * Process the results with a callback using minimal memory.
     */
    public function lazyEach(Closure $callback, int $chunkSize = 1000): void
    {
        $this->useLazyCollection = true;

        // Apply all filters to the builder if not already applied
        if ($this->state !== 'applied') {
            $this->apply($this->getBuilder(), $this->options);
        }

        // Process each item with minimal memory usage
        $this->getBuilder()->lazy($chunkSize)->each($callback);
    }

    /**
     * Create a generator to iterate through results with minimal memory usage.
     */
    public function cursor(): Generator
    {
        // Apply all filters to the builder if not already applied
        if ($this->state !== 'applied') {
            $this->apply($this->getBuilder(), $this->options);
        }

        // Use cursor for low memory iteration
        return $this->getBuilder()->cursor();
    }

    /**
     * Enable or disable lazy collection usage.
     */
    public function useLazy(bool $useLazy = true): self
    {
        $this->useLazyCollection = $useLazy;

        return $this;
    }

    /**
     * Map over the query results without loading all records.
     */
    public function map(callable $callback, int $chunkSize = 1000): array
    {
        $result = [];

        $this->lazyEach(function ($item) use (&$result, $callback) {
            $result[] = $callback($item);
        }, $chunkSize);

        return $result;
    }

    /**
     * Filter the results without loading all records.
     */
    public function filter(callable $callback, int $chunkSize = 1000): array
    {
        $result = [];

        $this->lazyEach(function ($item) use (&$result, $callback) {
            if ($callback($item)) {
                $result[] = $item;
            }
        }, $chunkSize);

        return $result;
    }

    /**
     * Reduce the results without loading all records.
     */
    public function reduce(callable $callback, $initial = null, int $chunkSize = 1000)
    {
        $result = $initial;

        $this->lazyEach(function ($item) use (&$result, $callback) {
            $result = $callback($result, $item);
        }, $chunkSize);

        return $result;
    }

    /**
     * Process the query in chunks using a callback.
     */
    public function chunk(int $chunkSize, Closure $callback): bool
    {
        // Apply all filters to the builder if not already applied
        if ($this->state !== 'applied') {
            $this->apply($this->builder, $this->options);
        }

        return $this->getBuilder()->chunk($chunkSize, $callback);
    }

    /**
     * Execute the query with memory management.
     * This method is called from the Filter::get() method when memory management is enabled.
     */
    protected function executeQueryWithMemoryManagement(): Collection
    {
        $chunkSize = $this->options['chunk_size'] ?? 1000;

        // If we need the full collection but want to load it in chunks
        // to avoid memory issues
        $collection = new Collection;

        $this->getBuilder()->chunk($chunkSize, function ($results) use ($collection) {
            $collection->push(...$results);
        });

        return $collection;
    }
}
