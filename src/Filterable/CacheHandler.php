<?php

namespace Filterable;

use Closure;
use Filterable\Interfaces\Handler;
use Illuminate\Contracts\Cache\Factory as CacheManager;
use Illuminate\Database\Eloquent\Builder;

class CacheHandler implements Handler
{
    /**
     * Create a new cache handler instance.
     *
     * @param \Illuminate\Contracts\Cache\Factory $cache
     * @param string                              $key
     * @param int                                 $duration
     */
    public function __construct(
        protected CacheManager $cache,
        protected string $key,
        protected int $duration = 5
    ) {
    }

    /**
     * Handle the request.
     *
     * @param Closure    $callback
     * @param array|null $options
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function handle(Closure $callback, ?array $options = []): Builder
    {
    }

    /**
     * Get the cache key.
     *
     * @return string
     */
    public function getCacheKey(): string
    {
        return $this->key;
    }

    /**
     * Set the cache key.
     *
     * @param string $key
     */
    public function setCacheKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Get the cache duration.
     *
     * @return int
     */
    public function getCacheDuration(): int
    {
        return $this->duration;
    }

    /**
     * Set the cache duration.
     *
     * @param int $duration
     */
    public function setCacheDuration(int $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    /**
     * Get the cache store.
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    public function getCacheStore()
    {
        return $this->cache->store();
    }
}
