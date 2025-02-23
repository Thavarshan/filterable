<?php

namespace Filterable\Interfaces;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Builder;
use Psr\Log\LoggerInterface;

interface Filter
{
    /**
     * Apply the filters.
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
     */
    public function appendFilterable(string $key, mixed $value): self;

    /**
     * Filter the query to only include records for the authenticated user.
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
     */
    public function registerPreFilters(Closure $callback): self;

    /**
     * Apply all relevant filters to the query and present it
     * as a callable for use within a collection instance.
     *
     *
     * @see https://laravel.com/docs/10.x/collections#method-filter
     */
    public function asCollectionFilter(): Closure;

    /**
     * Get expiration minutes for the cache.
     */
    public function getCacheExpiration(): int;

    /**
     * Set expiration minutes for the cache.
     *
     * @param  int  $value  Expiration minutes for the cache.
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
     * @param  array<string, mixed>  $options
     */
    public function setOptions(array $options): self;

    /**
     * Get the Eloquent builder instance.
     */
    public function getBuilder(): Builder;

    /**
     * Set the Eloquent builder instance.
     */
    public function setBuilder(Builder $builder): self;

    /**
     * Set the Logger instance.
     */
    public function setLogger(LoggerInterface $logger): self;

    /**
     * Get the Logger instance.
     */
    public function getLogger(): LoggerInterface;

    /**
     * Enable logging.
     */
    public static function enableLogging(): void;

    /**
     * Disable logging.
     */
    public static function disableLogging(): void;

    /**
     * Get indicates if logging should be used.
     */
    public static function shouldLog(): bool;

    /**
     * Set whether to use cache.
     */
    public static function enableCaching(?bool $useCache = true): void;

    /**
     * Disable caching.
     */
    public static function disableCaching(): void;

    /**
     * Clear the cache.
     */
    public function clearCache(): void;

    /**
     * Get the value of cache
     */
    public function getCacheHandler(): Cache;

    /**
     * Set the value of cache
     */
    public function setCacheHandler(Cache $cache): self;

    /**
     * Get indicates if caching should be used.
     */
    public static function shouldCache(): bool;
}
