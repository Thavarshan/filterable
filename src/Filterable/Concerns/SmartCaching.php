<?php

namespace Filterable\Concerns;

use Carbon\Carbon;
use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ReflectionObject;

trait SmartCaching
{
    /**
     * Cache tags to use.
     *
     * @var array<string>
     */
    protected array $cacheTags = [];

    /**
     * Whether to cache query results.
     */
    protected bool $shouldCacheResults = false;

    /** Null preserves automatic mode and legacy protected-property defaults. */
    protected ?bool $resultCachePolicy = null;

    /**
     * Whether to cache query count only.
     */
    protected bool $shouldCacheCount = false;

    /**
     * Set cache tags to use for better invalidation.
     */
    /** @param list<string>|string $tags */
    public function cacheTags(array|string $tags): self
    {
        if (is_string($tags)) {
            $tags = [$tags];
        }

        $this->cacheTags = $tags;

        return $this;
    }

    /**
     * Enable query result caching.
     */
    public function cacheResults(bool $shouldCache = true): self
    {
        $this->shouldCacheResults = $shouldCache;
        $this->resultCachePolicy = $shouldCache;

        return $this;
    }

    /** Restore heuristic result caching without enabling the caching concern. */
    public function autoCacheResults(): self
    {
        $this->shouldCacheResults = false;
        $this->resultCachePolicy = null;

        return $this;
    }

    /**
     * Enable query count caching.
     */
    public function cacheCount(bool $shouldCache = true): self
    {
        $this->shouldCacheCount = $shouldCache;

        return $this;
    }

    /**
     * Get the SQL for the current query for debugging.
     */
    public function toSql(): string
    {
        return $this->resolveBuilder()->toSql();
    }

    /**
     * Get count with caching.
     */
    public function count(): int
    {
        if (! $this->hasFeature('caching') ||
            ! $this->shouldCacheCount || $this->getCacheExpiration() === 0 || $this->hasQueryCallbacks()) {
            return $this->resolveBuilder()->count();
        }

        $cache = $this->getTaggedCache();
        $cacheKey = $this->buildCacheKey().':count';

        return $cache->remember(
            $cacheKey,
            Carbon::now()->addMinutes($this->getCacheExpiration()),
            function (): int {
                return $this->resolveBuilder()->count();
            }
        );
    }

    /**
     * Clear related caches when models change.
     */
    public function clearRelatedCaches(string $modelClass): void
    {
        if (empty($this->cacheTags)) {
            return;
        }

        $taggedCache = $this->getTaggedCache();

        if ($taggedCache instanceof TaggedCache) {
            $taggedCache->flush();
        }
    }

    /**
     * Get a tagged cache instance if supported.
     */
    protected function getTaggedCache(): Cache
    {
        $cache = $this->getCacheHandler();

        if (method_exists($cache, 'supportsTags') && $cache->supportsTags() && ! empty($this->cacheTags)) {
            return $cache->tags($this->cacheTags);
        }

        return $cache;
    }

    /**
     * Analyze a query to determine if it would benefit from caching.
     */
    protected function shouldAutomaticallyCacheQuery(): bool
    {
        // Skip caching for very simple queries that are fast anyway
        $simpleOperations = ['=', '>', '<', '>=', '<='];
        $queryWheres = $this->resolveBuilder()->getQuery()->wheres;

        // If there's only one simple where clause, don't bother caching
        if (count($queryWheres) <= 1) {
            foreach ($queryWheres as $where) {
                if (is_array($where) && isset($where['operator']) && in_array($where['operator'], $simpleOperations)) {
                    return false;
                }
            }
        }

        // Cache if there are joins, subqueries, or multiple where clauses
        return count($queryWheres) > 1 ||
               ! empty($this->resolveBuilder()->getQuery()->joins) ||
               strpos($this->toSql(), 'select') !== false;
    }

    /**
     * Smart implementation of executeQueryWithCaching.
     * This method is called from InteractsWithCache::smartExecuteQueryWithCaching.
     *
     * @return \Illuminate\Support\Collection<*, *>
     */
    protected function smartExecuteQueryWithCaching(): Collection
    {
        // If caching is disabled or shouldn't be used for this query
        if (! $this->hasFeature('caching') ||
            $this->getCacheExpiration() === 0 ||
            $this->resolveBuilder()->getEagerLoads() !== [] ||
            $this->hasQueryCallbacks() ||
            ! ($this->resultCachePolicy ?? ($this->shouldCacheResults || $this->shouldAutomaticallyCacheQuery()))) {
            return $this->resolveBuilder()->get();
        }

        $cache = $this->getTaggedCache();
        $cacheKey = $this->buildCacheKey();

        return $cache->remember(
            $cacheKey,
            Carbon::now()->addMinutes($this->getCacheExpiration()),
            function (): Collection {
                // Log actual DB query when building cache
                if ($this->hasFeature('logging')) {
                    $this->logInfo('Building cache for query', [
                        'sql' => $this->toSql(),
                        'bindings' => $this->resolveBuilder()->getBindings(),
                    ]);
                }

                return $this->resolveBuilder()->get();
            }
        );
    }

    /**
     * Callback output and side effects belong to the current get(), not a cached query.
     * Laravel 11–13 expose registration but no public callback-list getter. Inspect
     * the scoped clone and its base query without invoking or caching any closures.
     */
    protected function hasQueryCallbacks(): bool
    {
        $builder = $this->resolveBuilder()->applyScopes();
        if ($builder->getEagerLoads() !== []) {
            return true;
        }
        foreach ([$builder, $builder->getQuery()] as $query) {
            $reflection = new ReflectionObject($query);
            foreach (['afterQueryCallbacks', 'beforeQueryCallbacks'] as $property) {
                if ($reflection->hasProperty($property) && $reflection->getProperty($property)->getValue($query) !== []) {
                    return true;
                }
            }
        }

        return false;
    }
}
