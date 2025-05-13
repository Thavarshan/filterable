<?php

namespace Filterable\Tests\Fixtures;

use Filterable\Filter;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

class TestFilter extends Filter
{
    /**
     * Cache expiration time in minutes.
     */
    protected int $cacheExpiration = 60;

    /**
     * Create a new filter instance.
     */
    public function __construct(
        protected Request $request,
        ?Cache $cache = null,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($request, $cache, $logger);
    }

    /**
     * Build a cache key.
     */
    public function buildCacheKey(): string
    {
        $key = 'filter:test';

        if (! empty($this->filterables)) {
            $key .= ':'.md5(serialize($this->filterables));
        }

        return $key;
    }

    /**
     * Get the cache expiration time in minutes.
     */
    public function getCacheExpiration(): int
    {
        return $this->cacheExpiration;
    }

    /**
     * Set the cache expiration time in minutes.
     */
    public function setCacheExpiration(int $minutes): self
    {
        $this->cacheExpiration = $minutes;

        return $this;
    }
}
