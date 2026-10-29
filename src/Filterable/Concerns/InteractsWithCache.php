<?php

namespace Filterable\Concerns;

use Filterable\Contracts\Filter;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Collection;
use InvalidArgumentException;

trait InteractsWithCache
{
    /**
     * Expiration minutes for the cache.
     */
    protected int $cacheExpiration = 5;

    /**
     * The cache repository instance.
     */
    protected ?Cache $cache = null;

    /**
     * Get expiration minutes for the cache.
     */
    public function getCacheExpiration(): int
    {
        return $this->cacheExpiration;
    }

    /**
     * Set expiration minutes for the cache.
     */
    public function setCacheExpiration(int $minutes): Filter
    {
        if ($minutes < 0) {
            throw new InvalidArgumentException('Cache TTL must be zero or greater.');
        }
        $this->cacheExpiration = $minutes;

        return $this;
    }

    /**
     * Get the cache handler.
     */
    public function getCacheHandler(): Cache
    {
        if (is_null($this->cache)) {
            $this->cache = app(Cache::class);
        }

        return $this->cache;
    }

    /**
     * Set the cache handler.
     */
    public function setCacheHandler(Cache $cache): Filter
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Clear the cache.
     */
    public function clearCache(): void
    {
        if ($this->hasFeature('logging')) {
            $this->getLogger()->info('Clearing cache for filter', [
                'cache_key' => $this->buildCacheKey(),
            ]);
        }

        $this->getTaggedCache()->forget($this->buildCacheKey());
        $this->getTaggedCache()->forget($this->buildCacheKey().':count');
    }

    /**
     * Apply the filterables to the query with caching.
     */
    protected function applyFilterablesWithCache(): void
    {
        $this->applyFiltersToQuery();
    }

    /**
     * Build the cache key for the filter.
     */
    protected function buildCacheKey(): string
    {
        if ($this->builder !== null) {
            $builder = $this->builder;
            $connection = $builder->getConnection();

            return 'filters:'.hash('sha256', serialize([
                static::class, $builder->getModel()::class,
                $builder->getModel()->getConnectionName(), $connection->getDatabaseName(),
                $builder->toSql(), $builder->getBindings(), $builder->getEagerLoads() === [] ? [] : array_keys($builder->getEagerLoads()),
                $this->options['cache_context'] ?? null,
                $this->forUser?->getAuthIdentifier(),
                ($this->actorSet ? $this->authorizationActor : $this->request->user())?->getAuthIdentifier(),
            ]));
        }

        // Before apply there is no query identity yet. This path is useful for diagnostics only.
        return 'filters:'.hash('sha256', static::class);
    }

    /**
     * Execute a query with caching if needed.
     * This method delegates to SmartCaching if available.
     *
     * @return \Illuminate\Support\Collection<*, *>
     */
    protected function executeQueryWithCaching(): Collection
    {
        return $this->smartExecuteQueryWithCaching();
    }
}
