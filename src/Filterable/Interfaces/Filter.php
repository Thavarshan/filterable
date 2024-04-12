<?php

namespace Filterable;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

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
interface Filter
{
    /**
     * Apply the filters.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param array|null                            $options
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Builder $builder, ?array $options = []): Builder;

    /**
     * Fetch all relevant filters (key, value) from the request.
     *
     * @return array<string>
     */
    public function getFilterables(): array;

    /**
     * Get the registered filters.
     *
     * @return array<string, mixed>
     */
    public function getFilters(): array;

    /**
     * Append a filterable value to the filter.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return self
     */
    public function appendFilterable(string $key, mixed $value): self;

    /**
     * Filter the query to only include records for the authenticated user.
     *
     * @param \Illuminate\Contracts\Auth\Authenticatable|null $user
     *
     * @return self
     */
    public function forUser(?Authenticatable $user): self;

    /**
     * Get the current filters being applied.
     *
     * @return array<string>
     */
    public function getCurrentFilters(): array;

    /**
     * Register pre-filters to apply to the query.
     *
     * @param Closure $callback
     *
     * @return self
     */
    public function registerPreFilters(Closure $callback): self;

    /**
     * Apply all relevant filters to the query and present it
     * as a callable for use within a collection instance.
     *
     * @return Closure
     *
     * @see https://laravel.com/docs/10.x/collections#method-filter
     */
    public function asCollectionFilter(): Closure;

    /**
     * Get expiration minutes for the cache.
     *
     * @return int
     */
    public function getCacheExpiration(): int;

    /**
     * Set expiration minutes for the cache.
     *
     * @param int $value Expiration minutes for the cache.
     *
     * @return self
     */
    public function setCacheExpiration(int $value): self;

    /**
     * Get the extra options for the filter.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array;

    /**
     * Set the extra options for the filter.
     *
     * @param array<string, mixed> $options
     *
     * @return self
     */
    public function setOptions(array $options): self;

    /**
     * Get the Eloquent builder instance.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getBuilder(): Builder;

    /**
     * Set the Eloquent builder instance.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return self
     */
    public function setBuilder(Builder $builder): self;

    /**
     * Set whether to use cache.
     *
     * @param bool $useCache
     *
     * @return self
     */
    public function setUseCache(bool $useCache): self;

    /**
     * Clear the cache.
     *
     * @return void
     */
    public function clearCache(): void;

    /**
     * Get the value of cache
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    public function getCacheHandler(): Cache;

    /**
     * Set the value of cache
     *
     * @param \Illuminate\Contracts\Cache\Repository $cache
     *
     * @return self
     */
    public function setCacheHandler(Cache $cache): self;

    /**
     * Get indicates if caching should be used.
     *
     * @return bool
     */
    public function shouldUseCache(): bool;
}
