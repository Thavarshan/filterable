<?php

namespace Filterable\Concerns;

use Closure;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;
use RuntimeException;

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
        $chunkSize = $this->resolveChunkSize($chunkSize);
        $this->useLazyCollection = true;
        $this->options['chunk_size'] = $chunkSize;

        $this->ensureApplied();

        // Return a lazy collection that loads records in chunks
        return $this->resolveBuilder()->lazy($chunkSize);
    }

    /**
     * Process the results with a callback using minimal memory.
     */
    public function lazyEach(Closure $callback, int $chunkSize = 1000): void
    {
        $chunkSize = $this->resolveChunkSize($chunkSize);
        $this->useLazyCollection = true;
        $this->options['chunk_size'] = $chunkSize;

        $this->ensureApplied();

        // Process each item with minimal memory usage
        $this->resolveBuilder()->lazy($chunkSize)->each($callback);
    }

    /**
     * Create a generator to iterate through results with minimal memory usage.
     */
    public function cursor(): Generator
    {
        $this->ensureApplied();

        // Use cursor for low memory iteration
        return $this->resolveBuilder()->cursor();
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
        $chunkSize = $this->resolveChunkSize($chunkSize);
        $this->ensureApplied();

        return $this->resolveBuilder()->chunk($chunkSize, $callback);
    }

    /**
     * Stream the query results as a lazy collection.
     */
    public function stream(?int $chunkSize = null): LazyCollection
    {
        $chunkSize = $this->resolveChunkSize($chunkSize);
        $this->useLazyCollection = true;
        $this->options['chunk_size'] = $chunkSize;

        $this->ensureStreamReady();

        return $this->resolveBuilder()->lazy($chunkSize);
    }

    /**
     * Stream the query results as a native generator.
     */
    public function streamGenerator(?int $chunkSize = null): Generator
    {
        foreach ($this->stream($chunkSize) as $item) {
            yield $item;
        }
    }

    /**
     * Execute the query with memory management.
     * This method is called from the Filter::get() method when memory management is enabled.
     */
    protected function executeQueryWithMemoryManagement(): Collection
    {
        $chunkSize = $this->resolveChunkSize();

        $collection = new Collection;

        foreach ($this->stream($chunkSize) as $model) {
            $collection->push($model);
        }

        return $collection;
    }

    /**
     * Ensure the builder has been applied before streaming operations.
     */
    protected function ensureStreamReady(): void
    {
        if ($this->state === 'initialized') {
            throw new RuntimeException('You must call apply() before streaming results.');
        }

        if ($this->state === 'failed') {
            throw new RuntimeException(
                'Filters failed to apply: '.$this->lastException?->getMessage(),
                0,
                $this->lastException
            );
        }
    }

    /**
     * Ensure the query has been applied to the builder, if possible.
     */
    protected function ensureApplied(): void
    {
        if ($this->state !== 'applied' && $this->getBuilder()) {
            $this->apply($this->getBuilder(), $this->options);
        }
    }

    /**
     * Resolve the builder instance or throw if it is unavailable.
     */
    protected function resolveBuilder(): Builder
    {
        $builder = $this->getBuilder();

        if (! $builder) {
            throw new RuntimeException('A query builder instance is required for memory-managed operations.');
        }

        return $builder;
    }

    /**
     * Resolve the chunk size for memory-managed operations.
     */
    protected function resolveChunkSize(?int $chunkSize = null): int
    {
        $size = $chunkSize ?? $this->options['chunk_size'] ?? 1000;

        return max(1, (int) $size);
    }
}
