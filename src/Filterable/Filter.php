<?php

namespace Filterable;

use BadMethodCallException;
use Carbon\Carbon;
use Closure;
use Filterable\Interfaces\Filter as FilterInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// phpcs:disable
/**
 * Class Filter
 *
 * This class is used to apply filters to an Eloquent query builder instance.
 * It provides methods to set and apply filters, cache the results, and filter results for a specific authenticated user.
 *
 * @package Filterable
 *
 * @property \Illuminate\Database\Eloquent\Builder           $builder         The Eloquent builder instance.
 * @property \Illuminate\Http\Request                        $request         The current HTTP request.
 * @property \Illuminate\Contracts\Cache\Repository          $cache           The cache repository instance.
 * @property array<string>                                   $filters         The registered filters.
 * @property array<string, mixed>                            $filterables     The current filters being applied.
 * @property array<string>                                   $currentFilters  The current filters being applied.
 * @property int                                             $cacheExpiration The cache expiration time.
 * @property \Illuminate\Contracts\Auth\Authenticatable|null $forUser         The authenticated user to filter by.
 * @property Closure|null                                    $preFilters      The pre-filters to apply to the query.
 * @property array<string, mixed>                            $options         Extra options for the filter.
 * @property array<string, string>                           $filterMethodMap The map of filter methods.
 *
 * @method void          applyFilterable(mixed $value, string $filter) Applies a specific filter to the query.
 * @method void          applyForUserFilter()                          Applies a filter for a specific user if it's set.
 * @method void          applyPreFilters()                             Applies the pre-filters to the query.
 * @method void          applyFilterables()                            Applies filters to the query and caches the results if caching is enabled.
 * @method string        buildCacheKey()                               Builds a unique cache key based on the filters and the authenticated user.
 * @method array<string> getFilterables()                              Returns the registered filters.
 * @method array<string> getFilters()                                  Returns the filters from the request.
 * @method self          appendFilterable(string $key, mixed $value)   Adds a filterable value to the filter.
 * @method self          forUser(?Authenticatable $user)               Sets the authenticated user to filter by.
 * @method array<string> getCurrentFilters()                           Returns the current filters being applied.
 * @method self          registerPreFilters(Closure $callback)         Registers pre-filters to apply to the query.
 * @method int           getCacheExpiration()                          Gets the cache expiration time.
 * @method self          setCacheExpiration(int $value)                Sets the cache expiration time.
 * @method void          apply(Builder $builder)                       The main entry point to apply all filters to the builder instance.
 * @method array<string> getOptions()                                  Gets extra options for the filter.
 * @method self          setOptions(array<string, mixed> $options)     Sets extra options for the filter.
 * @method Builder       getBuilder()                                  Gets the Eloquent builder instance.
 * @method self          setBuilder(Builder $builder)                  Sets the Eloquent builder instance.
 *
 * @see \Filterable\Interfaces\Filter
 */
// phpcs:enable
abstract class Filter implements FilterInterface
{
    /**
     * The Eloquent builder instance.
     *
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected Builder $builder;

    /**
     * The pre-filters to apply to the query.
     *
     * @var Closure|null
     */
    protected ?Closure $preFilters = null;

    /**
     * Registered filters to operate upon.
     *
     * @var array<string>
     */
    protected array $filters = [];

    /**
     * Map of filter names to methods.
     *
     * @var array<string, string>
     */
    protected array $filterMethodMap = [];

    /**
     * All filters that have been chosed to be applied.
     *
     * @var array<string, mixed>
     */
    protected array $filterables = [];

    /**
     * The current filters being applied.
     *
     * @var array<string>
     */
    protected array $currentFilters = [];

    /**
     * Extra options for the filter.
     *
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * Indicates if caching should be used.
     *
     * @var bool
     */
    protected bool $useCache = true;

    /**
     * Expiration minutes for the cache.
     *
     * @var int
     */
    protected int $cacheExpiration = 5;

    /**
     * The authenticated user to filter by.
     *
     * @var \Illuminate\Contracts\Auth\Authenticatable|null
     */
    protected ?Authenticatable $forUser = null;

    /**
     * Create a new filter instance.
     *
     * @param \Illuminate\Http\Request               $request
     * @param \Illuminate\Contracts\Cache\Repository $cache
     *
     * @return void
     */
    public function __construct(
        protected Request $request,
        protected Cache $cache
    ) {
    }

    /**
     * Apply the filters.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param array|null                            $options
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Builder $builder, ?array $options = []): Builder
    {
        // Set the builder and options
        $this->setOptions($options)->setBuilder($builder);

        // Apply the filter for a specific user if it's set
        $this->applyForUserFilter();

        // Apply any predefined filters
        $this->applyPreFilters();

        // Apply the filters that are defined dynamically
        $this->applyFilterables();

        // Return the Builder instance with all the filters applied
        return $this->getBuilder();
    }

    /**
     * Apply the for user filter.
     *
     * @return void
     */
    protected function applyForUserFilter(): void
    {
        if (! is_null($this->forUser)) {
            $this->getBuilder()->where(
                $this->forUser->getAuthIdentifierName(),
                $this->forUser->getAuthIdentifier()
            );
        }
    }

    /**
     * Apply the filterables to the query.
     *
     * @return void
     */
    protected function applyFilterables(): void
    {
        if (! $this->useCache) {
            $this->applyFiltersToQuery();

            return;
        }

        $this->cache->remember(
            $this->buildCacheKey(),
            Carbon::now()->addMinutes($this->getCacheExpiration()),
            function () {
                $this->applyFiltersToQuery();

                return $this->getBuilder()->get();
            }
        );
    }

    /**
     * Apply the filters to the query.
     *
     * @return void
     */
    protected function applyFiltersToQuery(): void
    {
        collect($this->getFilterables())
            ->filter(fn (mixed $value) => (! is_string($value) && ! is_null($value))
                && $value !== ''
                && $value !== false
                && $value !== [])
            ->each(function ($value, $filter) {
                $this->applyFilterable($value, $filter);
            });
    }

    /**
     * Apply a filter to the query.
     *
     * @param mixed  $value
     * @param string $filter
     *
     * @return void
     */
    protected function applyFilterable(mixed $value, string $filter): void
    {
        $filter = $this->filterMethodMap[$filter]
            ?? Str::camel($filter);

        if (! method_exists($this, $filter)) {
            throw new BadMethodCallException(
                sprintf('Method %s does not exist on %s', $filter, static::class)
            );
        }

        call_user_func([$this, $filter], $value);
    }

    /**
     * Build the cache key for the filter.
     *
     * @return string
     */
    protected function buildCacheKey(): string
    {
        // Create a unique cache key based on the filterables and any
        // other relevant context, such as authenticated user
        $userPart = optional($this->forUser)->getAuthIdentifier() ?? 'global';
        $filtersPart = http_build_query($this->getFilterables());

        return "filters:{$userPart}:{$filtersPart}";
    }

    /**
     * Fetch all relevant filters from the request.
     *
     * @return array<string>
     */
    public function getFilterables(): array
    {
        $filterKeys = array_merge(
            $this->filters,
            array_keys($this->filterMethodMap ?? [])
        );

        $this->filterables = array_merge(
            $this->filterables,
            array_filter($this->request->only($filterKeys))
        );

        $this->currentFilters = array_keys($this->filterables);

        return $this->filterables;
    }

    /**
     * Get the registered filters.
     *
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Append a filterable value to the filter.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return self
     */
    public function appendFilterable(string $key, mixed $value): self
    {
        $this->filterables[$key] = $value;

        return $this;
    }

    /**
     * Filter the query to only include records for the authenticated user.
     *
     * @param \Illuminate\Contracts\Auth\Authenticatable|null $user
     *
     * @return self
     */
    public function forUser(?Authenticatable $user): self
    {
        $this->forUser = $user;

        return $this;
    }

    /**
     * Get the current filters being applied.
     *
     * @return array<string>
     */
    public function getCurrentFilters(): array
    {
        return $this->currentFilters;
    }

    /**
     * Register pre-filters to apply to the query.
     *
     * @param Closure $callback
     *
     * @return self
     */
    public function registerPreFilters(Closure $callback): self
    {
        $this->preFilters = $callback;

        return $this;
    }

    /**
     * Apply pre-filters to the query.
     *
     * @return void
     */
    protected function applyPreFilters(): void
    {
        if (! is_null($this->preFilters)) {
            call_user_func_array($this->preFilters, [$this->getBuilder()]);
        }
    }

    /**
     * Apply all relevant filters to the query and present it
     * as a callable for use within a collection instance.
     *
     * @return Closure
     *
     * @see https://laravel.com/docs/10.x/collections#method-filter
     */
    public function asCollectionFilter(): Closure
    {
        return fn (mixed $items) => collect($this->getFilterables());
    }

    /**
     * Get expiration minutes for the cache.
     *
     * @return int
     */
    public function getCacheExpiration(): int
    {
        return $this->cacheExpiration;
    }

    /**
     * Set expiration minutes for the cache.
     *
     * @param int $value Expiration minutes for the cache.
     *
     * @return self
     */
    public function setCacheExpiration(int $value): self
    {
        $this->cacheExpiration = $value;

        return $this;
    }

    /**
     * Get the extra options for the filter.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Set the extra options for the filter.
     *
     * @param array<string, mixed> $options
     *
     * @return self
     */
    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get the Eloquent builder instance.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getBuilder(): Builder
    {
        return $this->builder;
    }

    /**
     * Set the Eloquent builder instance.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return self
     */
    public function setBuilder(Builder $builder): self
    {
        $this->builder = $builder;

        return $this;
    }

    /**
     * Set whether to use cache.
     *
     * @param bool $useCache
     *
     * @return self
     */
    public function setUseCache(bool $useCache): self
    {
        $this->useCache = $useCache;

        return $this;
    }

    /**
     * Clear the cache.
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->cache->forget($this->buildCacheKey());
    }
}
