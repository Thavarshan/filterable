<?php

namespace Filterable\Interfaces;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Builder;
use Psr\Log\LoggerInterface;

// phpcs:disable
/**
 * Interface Filter
 *
 * Defines the contract for classes that implement advanced filtering mechanisms on Eloquent query builders.
 * This interface includes methods to apply dynamic filters, handle user-specific filtering, apply pre-filters,
 * manage caching of query results, dynamically manipulate filter parameters based on HTTP requests or other conditions,
 * and incorporate logging for monitoring and debugging purposes.
 *
 * @package Filterable
 *
 * @method        void            apply(Builder $builder, ?array $options = []) Applies all filters to the provided Builder instance, potentially modified by options.
 * @method        array           getFilterables()                              Fetches all active filters along with their values, ready to be applied or analyzed.
 * @method        array           getFilters()                                  Retrieves all registered filter keys that can potentially influence the query.
 * @method        self            appendFilterable(string $key, mixed $value)   Adds or updates a filterable value, allowing dynamic changes to filter conditions.
 * @method        self            forUser(?Authenticatable $user)               Sets an authenticated user to tailor the filters based on user-specific criteria, enhancing data security and relevance.
 * @method        array           getCurrentFilters()                           Returns a list of currently active filters, useful for debugging and logging.
 * @method        self            registerPreFilters(Closure $callback)         Registers a closure containing pre-filters, which are applied before the main filters for preliminary data handling or modification.
 * @method        Closure         asCollectionFilter()                          Provides a Closure that applies all current filters within a collection context, useful for non-database filtering tasks.
 * @method        int             getCacheExpiration()                          Gets the currently set cache expiration time in minutes, influencing data freshness.
 * @method        self            setCacheExpiration(int $value)                Sets the cache expiration time, allowing dynamic control over data caching duration.
 * @method        array           getOptions()                                  Retrieves additional filter configuration options, providing flexibility in how filters are applied.
 * @method        self            setOptions(array $options)                    Sets additional filter options, enabling customization of filtering behavior.
 * @method        Builder         getBuilder()                                  Gets the current Eloquent query builder instance, the primary target for filter applications.
 * @method        self            setBuilder(Builder $builder)                  Sets the Eloquent builder instance, allowing the reuse of the interface in different contexts.
 * @method static void            enableCaching(bool $useCache = true)          Enables or disables caching globally across instances, providing control over performance optimization.
 * @method        void            clearCache()                                  Clears the cache for the current filter settings, essential for maintaining data accuracy when underlying data changes.
 * @method        Cache           getCacheHandler()                             Retrieves the cache handler instance, necessary for direct cache manipulations or inspections.
 * @method        self            setCacheHandler(Cache $cache)                 Sets the cache repository instance, facilitating custom cache strategies.
 * @method static bool            shouldCache()                                 Checks if caching is enabled, providing a boolean status that helps decide whether to use cached data.
 * @method        self            setLogger(LoggerInterface $logger)            Sets the logger instance, enabling customized logging strategies.
 * @method        LoggerInterface getLogger()                                   Gets the current logger instance, essential for executing logging operations.
 * @method static void            enableLogging()                               Enables logging functionality, useful for debugging and monitoring filter applications.
 * @method static void            disableLogging()                              Disables logging, helpful for improving performance when logging is unnecessary.
 * @method static bool            shouldLog()                                   Determines if logging is currently enabled, guiding conditional logging operations.
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
     * Set the Logger instance.
     *
     * @param \Psr\Log\LoggerInterface $logger
     *
     * @return self
     */
    public function setLogger(LoggerInterface $logger): self;

    /**
     * Get the Logger instance.
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger(): LoggerInterface;

    /**
     * Enable logging.
     *
     * @return void
     */
    public static function enableLogging(): void;

    /**
     * Disable logging.
     *
     * @return void
     */
    public static function disableLogging(): void;

    /**
     * Get indicates if logging should be used.
     *
     * @return bool
     */
    public static function shouldLog(): bool;

    /**
     * Set whether to use cache.
     *
     * @param bool $useCache
     *
     * @return void
     */
    public static function enableCaching(?bool $useCache = true): void;

    /**
     * Disable caching.
     *
     * @return void
     */
    public static function disableCaching(): void;

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
    public static function shouldCache(): bool;
}
