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
use Psr\Log\LoggerInterface;

// phpcs:disable
/**
 * Provides a comprehensive solution for applying dynamic and user-specific filters to Eloquent query builders based on HTTP request parameters.
 * It supports caching of results, pre-filtering, and dynamically invokes filter methods based on the filter keys provided in the request.
 *
 * This class is designed to enhance query customization while optimizing query execution through caching mechanisms and detailed logging capabilities,
 * making it suitable for applications requiring dynamic data retrieval based on user input.
 *
 * @package Filterable
 *
 * @property \Illuminate\Database\Eloquent\Builder           $builder         The Eloquent builder instance to apply filters to.
 * @property \Illuminate\Http\Request                        $request         The current HTTP request, providing context and parameters for filters.
 * @property \Illuminate\Contracts\Cache\Repository|null     $cache           Optional caching mechanism to store and retrieve results of filtered queries.
 * @property \Psr\Log\LoggerInterface|null                   $logger          Optional logging interface to log the process and results of filter applications.
 * @property array<string>                                   $filters         Registered filter keys that this class can handle.
 * @property array<string, mixed>                            $filterables     Active filters with their respective values extracted from the request.
 * @property array<string>                                   $currentFilters  A list of filters currently being applied to the query.
 * @property int                                             $cacheExpiration Duration in minutes for which the query results should be cached.
 * @property \Illuminate\Contracts\Auth\Authenticatable|null $forUser         Optional authenticated user to apply user-specific filters.
 * @property Closure|null                                    $preFilters      Optional pre-filters to apply before the main filters for additional query manipulation.
 * @property array<string, mixed>                            $options         Miscellaneous options that can affect the behavior of the filter processing.
 * @property array<string, string>                           $filterMethodMap Mapping of filter keys to their corresponding method names for dynamic invocation.
 *
 * @method void                                  applyFilterable(mixed $value, string $filter)              Dynamically applies a filter to the builder based on a specified method.
 * @method void                                  applyForUserFilter()                                       Applies filters specific to the authenticated user if available.
 * @method void                                  applyPreFilters()                                          Applies any registered pre-filters to the builder before main filters.
 * @method void                                  applyFilterables()                                         Applies all active filters to the builder and caches the results if caching is enabled.
 * @method string                                buildCacheKey()                                            Constructs a unique cache key based on active filters and user context, used for caching results.
 * @method array<string>                         getFilterables()                                           Retrieves all currently active filters along with their values.
 * @method array<string>                         getFilters()                                               Fetches a list of all registered filter keys from the request.
 * @method self                                  appendFilterable(string $key, mixed $value)                Adds or updates a filterable value to the current set of active filters.
 * @method self                                  forUser(?Authenticatable $user)                            Sets an authenticated user for user-specific filtering.
 * @method array<string>                         getCurrentFilters()                                        Returns a list of all filters that are currently being applied.
 * @method self                                  registerPreFilters(\Closure $callback)                     Registers a closure containing pre-filters for the query.
 * @method int                                   getCacheExpiration()                                       Gets the currently set cache expiration time.
 * @method self                                  setCacheExpiration(int $value)                             Sets the cache expiration time.
 * @method void                                  apply(\Illuminate\Database\Eloquent\Builder $builder)      Main method to apply all registered and valid filters to the builder.
 * @method array<string, mixed>                  getOptions()                                               Retrieves additional options for the filter.
 * @method self                                  setOptions(array<string, mixed> $options)                  Sets additional options that affect filtering behavior.
 * @method \Illuminate\Database\Eloquent\Builder getBuilder()                                               Gets the current Eloquent builder instance.
 * @method self                                  setBuilder(\Illuminate\Database\Eloquent\Builder $builder) Sets the builder instance on which filters will be applied.
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
     * Indicates if logging should be used.
     *
     * @var bool
     */
    protected static bool $shouldLog = false;

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
     * @param \Psr\Log\LoggerInterface|null               $logger
     *
     * @return void
     */
    public function __construct(
        protected Request $request,
        protected ?Cache $cache = null,
        protected ?LoggerInterface $logger = null
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
        if (is_null($this->forUser)) {
            return;
        }

        $attribute = $this->forUser->getAuthIdentifierName();
        $value = $this->forUser->getAuthIdentifier();

        if (self::shouldLog()) {
            $this->getLogger()->info('Applying user-specific filter', [
                $attribute => $attribute,
                'filter' => $value,
            ]);
        }

        $this->getBuilder()->where($attribute, $value);
    }

    /**
     * Apply the filterables to the query.
     *
     * @return void
     */
    protected function applyFilterables(): void
    {
        if (! self::shouldCache()) {
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

        if (self::shouldLog()) {
            $this->getLogger()->info("Applying filter method: {$filter}", [
                'filter' => $filter,
                'value' => $value,
            ]);
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
     * Set the Logger instance.
     *
     * @param \Psr\Log\LoggerInterface $logger
     *
     * @return self
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Get the Logger instance.
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger ?? app(LoggerInterface::class);
    }

    /**
     * Enable logging.
     *
     * @return void
     */
    public static function enableLogging(): void
    {
        self::$shouldLog = true;
    }

    /**
     * Disable logging.
     *
     * @return void
     */
    public static function disableLogging(): void
    {
        self::$shouldLog = false;
    }

    /**
     * Get indicates if logging should be used.
     *
     * @return bool
     */
    public static function shouldLog(): bool
    {
        return self::$shouldLog;
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
        if (self::shouldLog()) {
            $this->getLogger()->info('Clearing cache for filter', [
                'cache_key' => $this->buildCacheKey(),
            ]);
        }

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
            $this->cache = app(Cache::class);
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
    public static function shouldCache(): bool
    {
        return self::$useCache;
    }
}
