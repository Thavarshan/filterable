<?php

namespace Filterable;

use BadMethodCallException;
use Carbon\Carbon;
use Closure;
use Filterable\Interfaces\Filter as FilterInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// phpcs:disable
/**
 * Provides a flexible mechanism for applying filters to an Eloquent query builder based on HTTP request parameters.
 * It supports dynamic filter application, user-specific filtering, pre-filtering, caching of results, and offers
 * additional configuration options for advanced filtering needs.
 *
 * Functionalities include setting up the query builder, managing filters, handling user-based filters, applying pre-filters,
 * caching query results with custom expiration, and dynamic method invocation based on filter names.
 *
 * @package Filterable
 *
 * @property Builder               $builder         The Eloquent builder instance.
 * @property Request               $request         The current HTTP request.
 * @property Cache|null            $cache           The cache repository instance, if caching is enabled.
 * @property array<string>         $filters         List of registered filter keys.
 * @property array<string, mixed>  $filterables     The current filters being applied with their values.
 * @property array<string>         $currentFilters  List of filters currently being applied.
 * @property int                   $cacheExpiration Cache expiration time in minutes.
 * @property Authenticatable|null  $forUser         The authenticated user, if filtering is to be user-specific.
 * @property Closure|null          $preFilters      Closure containing pre-filters to be applied before the main filters.
 * @property array<string, mixed>  $options         Additional options for filter behavior.
 * @property array<string, string> $filterMethodMap Mapping of filter keys to method names for dynamic invocation.
 *
 * @method void                 applyFilterable(mixed $value, string $filter) Applies a specific filter to the query.
 * @method void                 applyForUserFilter()                          Applies user-specific filters based on the authenticated user.
 * @method void                 applyPreFilters()                             Applies pre-defined pre-filters to the query.
 * @method void                 applyFilterables()                            Applies all active filters to the query and handles caching if enabled.
 * @method string               buildCacheKey()                               Generates a unique cache key based on active filters and user context.
 * @method array<string>        getFilterables()                              Fetches filters and their values from the request and registers them.
 * @method array<string>        getFilters()                                  Retrieves filter keys from the request.
 * @method self                 appendFilterable(string $key, mixed $value)   Adds or updates a filter and its value.
 * @method self                 forUser(?Authenticatable $user)               Sets the authenticated user for user-specific filtering.
 * @method array<string>        getCurrentFilters()                           Returns a list of currently applied filters.
 * @method self                 registerPreFilters(Closure $callback)         Registers a closure containing pre-filters.
 * @method int                  getCacheExpiration()                          Retrieves the current cache expiration setting.
 * @method self                 setCacheExpiration(int $value)                Sets the cache expiration time.
 * @method void                 apply(Builder $builder)                       Main method to apply all filters to the provided builder instance.
 * @method array<string, mixed> getOptions()                                  Retrieves additional filter options.
 * @method self                 setOptions(array<string, mixed> $options)     Sets additional filter options.
 * @method Builder              getBuilder()                                  Retrieves the current Eloquent builder instance.
 * @method self                 setBuilder(Builder $builder)                  Sets the Eloquent builder instance.
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
     * These are filters to be applied before the actual filterables are executed.
     * So when the Query Builder is run, the results have has already been
     * filtered and then the actual filters are applied.
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
     * These options are for the developers use and are not used internally.
     *
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * Indicates if caching should be used.
     *
     * @var bool
     */
    protected static bool $useCache = true;

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
     * @param \Illuminate\Http\Request                    $request
     * @param \Illuminate\Contracts\Cache\Repository|null $cache
     *
     * @return void
     */
    public function __construct(
        protected Request $request,
        protected ?Cache $cache = null
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
        // Basically to filter resutls by 'user_id' column
        $this->applyForUserFilter();

        // Apply any predefined filters
        $this->applyPreFilters();

        // Apply the filters that are defined dynamically
        $this->applyFilterables();

        // Return the Builder instance with all the filters applied
        return $this->getBuilder();
    }

    /**
     * Filter the query to only include records for the authenticated user.
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
        if (! self::shouldUseCache()) {
            $this->applyFiltersToQuery();

            return;
        }

        $this->getCacheHandler()->remember(
            $this->buildCacheKey(),
            Carbon::now()->addMinutes($this->getCacheExpiration()),
            function (): Collection {
                $this->applyFiltersToQuery();

                return $this->getBuilder()->get();
            }
        );
    }

    /**
     * Execute the query builder query functionality with the filters applied.
     *
     * @return void
     */
    protected function applyFiltersToQuery(): void
    {
        collect($this->getFilterables())
            ->filter(fn (mixed $value) => $value !== null
                && $value !== ''
                && $value !== false
                && $value !== [])
            ->each(function ($value, $filter) {
                $this->applyFilterable($filter, $value);
            });
    }

    /**
     * Apply a filter to the query.
     *
     * @param string $filter
     * @param mixed  $value
     *
     * @return void
     */
    protected function applyFilterable(string $filter, mixed $value): void
    {
        $filter = $this->makeFilterIntoMethodName($filter);

        if (! method_exists($this, $filter)) {
            throw new BadMethodCallException(
                sprintf('Method [%s] does not exist on %s', $filter, static::class)
            );
        }

        call_user_func([$this, $filter], $value);
    }

    /**
     * Make the filter into a method name.
     *
     * @param string $filter
     *
     * @return string
     */
    protected function makeFilterIntoMethodName(string $filter): string
    {
        return $this->filterMethodMap[$filter] ?? Str::camel($filter);
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
     * Fetch all relevant filters (key, value) from the request.
     *
     * @return array<string>
     */
    public function getFilterables(): array
    {
        $filterKeys = array_merge(
            $this->getFilters(),
            array_keys($this->filterMethodMap ?? [])
        );

        // Will contains key, value pairs of the filters
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
     * @return void
     */
    public static function enableCaching(?bool $useCache = true): void
    {
        self::$useCache = $useCache;
    }

    /**
     * Disable caching.
     *
     * @return void
     */
    public static function disableCaching(): void
    {
        self::$useCache = false;
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

    /**
     * Get the value of cache
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    public function getCacheHandler(): Cache
    {
        if (is_null($this->cache)) {
            $this->cache = cache();
        }

        return $this->cache;
    }

    /**
     * Set the value of cache
     *
     * @param \Illuminate\Contracts\Cache\Repository $cache
     *
     * @return self
     */
    public function setCacheHandler(Cache $cache): self
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Get indicates if caching should be used.
     *
     * @return bool
     */
    public static function shouldUseCache(): bool
    {
        return self::$useCache;
    }
}
