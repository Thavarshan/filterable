<?php

namespace Filterable\Concerns;

use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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

    /**
     * Whether to cache query count only.
     */
    protected bool $shouldCacheCount = false;

    /**
     * Get a tagged cache instance if supported.
     */
    protected function getTaggedCache(): Cache
    {
        $cache = $this->getCacheHandler();

        if (method_exists($cache, 'tags') && ! empty($this->cacheTags)) {
            return $cache->tags($this->cacheTags);
        }

        return $cache;
    }

    /**
     * Set cache tags to use for better invalidation.
     */
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
        return $this->builder->toSql();
    }

    /**
     * Analyze a query to determine if it would benefit from caching.
     */
    protected function shouldAutomaticallyCacheQuery(): bool
    {
        // Skip caching for very simple queries that are fast anyway
        $simpleOperations = ['=', '>', '<', '>=', '<='];
        $queryWheres = $this->builder->getQuery()->wheres ?? [];

        // If there's only one simple where clause, don't bother caching
        if (count($queryWheres) <= 1) {
            foreach ($queryWheres as $where) {
                if (isset($where['operator']) && in_array($where['operator'], $simpleOperations)) {
                    return false;
                }
            }
        }

        // Cache if there are joins, subqueries, or multiple where clauses
        return count($queryWheres) > 1 ||
               ! empty($this->builder->getQuery()->joins) ||
               strpos($this->toSql(), 'select') !== false;
    }

    /**
     * Smart implementation of executeQueryWithCaching.
     * This method is called from InteractsWithCache::smartExecuteQueryWithCaching.
     */
    protected function smartExecuteQueryWithCaching(): Collection
    {
        // If caching is disabled or shouldn't be used for this query
        if (! self::shouldCache() ||
            (! $this->shouldCacheResults && ! $this->shouldAutomaticallyCacheQuery())) {
            return $this->builder->get();
        }

        $cache = $this->getTaggedCache();
        $cacheKey = $this->buildCacheKey();

        return $cache->remember(
            $cacheKey,
            Carbon::now()->addMinutes($this->getCacheExpiration()),
            function (): Collection {
                // Log actual DB query when building cache
                if (method_exists($this, 'logInfo')) {
                    $this->logInfo('Building cache for query', [
                        'sql' => $this->toSql(),
                        'bindings' => $this->builder->getBindings(),
                    ]);
                }

                return $this->builder->get();
            }
        );
    }

    /**
     * Get count with caching.
     */
    public function count(): int
    {
        if (! self::shouldCache() || ! $this->shouldCacheCount) {
            return $this->builder->count();
        }

        $cache = $this->getTaggedCache();
        $cacheKey = $this->buildCacheKey().':count';

        return $cache->remember(
            $cacheKey,
            Carbon::now()->addMinutes($this->getCacheExpiration()),
            function (): int {
                return $this->builder->count();
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

        if (method_exists($taggedCache, 'flush')) {
            $taggedCache->flush();
        }
    }
}
