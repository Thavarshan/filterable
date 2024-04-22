<?php

namespace Filterable\Interfaces;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Builder;

// phpcs:disable
/**
 * Interface Filter
 *
 * Defines the contract for classes that implement advanced filtering mechanisms on Eloquent query builders.
 * This interface allows for dynamic application of filters, handling of user-specific criteria, pre-filter application,
 * caching of results, and retrieval and modification of filtering options.
 *
 * Methods in this interface support setting and applying filters to a Builder instance, managing cache settings,
 * and manipulating filter parameters dynamically based on HTTP requests or predefined conditions.
 *
 * @package Filterable
 *
 * @method        void    apply(Builder $builder, ?array $options = []) Main method to apply all filters to the provided builder instance.
 * @method        array   getFilterables()                              Fetches all filters and their current values that can be applied to the query.
 * @method        array   getFilters()                                  Retrieves a list of all registered filter keys.
 * @method        self    appendFilterable(string $key, mixed $value)   Appends or updates a specific filter value.
 * @method        self    forUser(?Authenticatable $user)               Sets an authenticated user to filter the queries by user-specific criteria.
 * @method        array   getCurrentFilters()                           Returns a list of the currently active filters.
 * @method        self    registerPreFilters(Closure $callback)         Registers a closure with pre-filters to be applied before the main filters.
 * @method        Closure asCollectionFilter()                          Provides a Closure that filters a collection based on the current filter settings.
 * @method        int     getCacheExpiration()                          Returns the currently set cache expiration time in minutes.
 * @method        self    setCacheExpiration(int $value)                Sets the cache expiration time in minutes.
 * @method        array   getOptions()                                  Retrieves additional options set for filtering.
 * @method        self    setOptions(array $options)                    Sets additional options for filtering behavior.
 * @method        Builder getBuilder()                                  Gets the current Eloquent query builder instance.
 * @method        self    setBuilder(Builder $builder)                  Sets the Eloquent query builder instance.
 * @method static void    enableCaching(bool $useCache = true)          Enables or disables caching of filter results.
 * @method        void    clearCache()                                  Clears the cache for the current filter settings.
 * @method        Cache   getCacheHandler()                             Retrieves the cache handler instance.
 * @method        self    setCacheHandler(Cache $cache)                 Sets the cache handler instance.
 * @method static bool    shouldUseCache()                              Determines if caching should be used for filter results.
 *
 * @see \Illuminate\Database\Eloquent\Builder
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
     * @return void
     */
    public static function enableCaching(bool $useCache): void;

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
    public static function shouldUseCache(): bool;
}
