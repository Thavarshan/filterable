<?php

namespace Filterable\Concerns;

use Carbon\Carbon;
use Filterable\Contracts\Filter;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Collection;

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

        $this->getCacheHandler()->forget($this->buildCacheKey());
    }

    /**
     * Apply the filterables to the query with caching.
     */
    protected function applyFilterablesWithCache(): Collection
    {
        return $this->getCacheHandler()->remember(
            $this->buildCacheKey(),
            Carbon::now()->addMinutes($this->getCacheExpiration()),
            function (): Collection {
                $this->applyFiltersToQuery();

                return $this->getBuilder()->get();
            }
        );
    }

    /**
     * Build the cache key for the filter.
     */
    protected function buildCacheKey(): string
    {
        // Create a unique cache key with sanitized inputs
        $userPart = 'global';

        // Check if forUser property exists, is not null, and has getAuthIdentifier method
        if (property_exists($this, 'forUser') && $this->forUser !== null && method_exists($this->forUser, 'getAuthIdentifier')) {
            $userPart = $this->forUser->getAuthIdentifier() ?? 'global';
        }

        // Get the filterables, sort them by key, and normalize them
        $filterables = $this->getFilterables();
        ksort($filterables);

        // Sanitize values for use in cache keys
        $sanitizedFilterables = [];
        foreach ($filterables as $key => $value) {
            // Handle different data types appropriately
            if (is_array($value)) {
                $sanitizedValue = md5(json_encode($value));
            } elseif (is_scalar($value)) {
                $sanitizedValue = (string) $value;
            } else {
                $sanitizedValue = md5(serialize($value));
            }

            $sanitizedFilterables[$key] = $sanitizedValue;
        }

        $filtersPart = http_build_query($sanitizedFilterables);
        $cacheKey = "filters:{$userPart}:".md5($filtersPart);

        // Make sure the cache key isn't too long
        if (strlen($cacheKey) > 250) {
            $cacheKey = "filters:{$userPart}:".md5($filtersPart);
        }

        return $cacheKey;
    }

    /**
     * Execute a query with caching if needed.
     * This method delegates to SmartCaching if available.
     */
    protected function executeQueryWithCaching(): Collection
    {
        // If SmartCaching is loaded, use it
        if (method_exists($this, 'shouldAutomaticallyCacheQuery')) {
            return $this->smartExecuteQueryWithCaching();
        }

        // Otherwise, use basic caching
        if (! $this->hasFeature('caching')) {
            return $this->getBuilder()->get();
        }

        $cache = $this->getCacheHandler();
        $cacheKey = $this->buildCacheKey();

        return $cache->remember(
            $cacheKey,
            Carbon::now()->addMinutes($this->getCacheExpiration()),
            function (): Collection {
                return $this->getBuilder()->get();
            }
        );
    }
}
