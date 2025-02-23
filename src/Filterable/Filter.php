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

abstract class Filter implements FilterInterface
{
    /**
     * The Eloquent builder instance.
     */
    protected Builder $builder;

    /**
     * The pre-filters to apply to the query.
     *
     * These are filters to be applied before the actual filterables are executed.
     * So when the Query Builder is run, the results have has already been
     * filtered and then the actual filters are applied.
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
     */
    protected static bool $useCache = false;

    /**
     * Indicates if logging should be used.
     */
    protected static bool $shouldLog = false;

    /**
     * Expiration minutes for the cache.
     */
    protected int $cacheExpiration = 5;

    /**
     * The authenticated user to filter by.
     */
    protected ?Authenticatable $forUser = null;

    /**
     * Create a new filter instance.
     *
     * @return void
     */
    public function __construct(
        protected Request $request,
        protected ?Cache $cache = null,
        protected ?LoggerInterface $logger = null
    ) {}

    /**
     * Apply the filters.
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
     */
    protected function makeFilterIntoMethodName(string $filter): string
    {
        return $this->filterMethodMap[$filter] ?? Str::camel($filter);
    }

    /**
     * Build the cache key for the filter.
     */
    protected function buildCacheKey(): string
    {
        // Create a unique cache key based on the filterables and any
        // other relevant context, such as authenticated user
        $userPart = optional($this->forUser)->getAuthIdentifier() ?? 'global';

        // Get the filterables, sort them by key, and normalize them
        $filterables = $this->getFilterables();
        ksort($filterables);
        $filtersPart = http_build_query($filterables);

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
     */
    public function appendFilterable(string $key, mixed $value): self
    {
        $this->filterables[$key] = $value;

        return $this;
    }

    /**
     * Filter the query to only include records for the authenticated user.
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
     */
    public function registerPreFilters(Closure $callback): self
    {
        $this->preFilters = $callback;

        return $this;
    }

    /**
     * Apply pre-filters to the query.
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
     *
     * @see https://laravel.com/docs/10.x/collections#method-filter
     */
    public function asCollectionFilter(): Closure
    {
        return fn (mixed $items) => collect($this->getFilterables());
    }

    /**
     * Get expiration minutes for the cache.
     */
    public function getCacheExpiration(): int
    {
        return $this->cacheExpiration;
    }

    /**
     * Set expiration minutes for the cache.
     *
     * @param  int  $value  Expiration minutes for the cache.
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
     * @param  array<string, mixed>  $options
     */
    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get the Eloquent builder instance.
     */
    public function getBuilder(): Builder
    {
        return $this->builder;
    }

    /**
     * Set the Eloquent builder instance.
     */
    public function setBuilder(Builder $builder): self
    {
        $this->builder = $builder;

        return $this;
    }

    /**
     * Set the Logger instance.
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Get the Logger instance.
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger ?? app(LoggerInterface::class);
    }

    /**
     * Enable logging.
     */
    public static function enableLogging(): void
    {
        self::$shouldLog = true;
    }

    /**
     * Disable logging.
     */
    public static function disableLogging(): void
    {
        self::$shouldLog = false;
    }

    /**
     * Get indicates if logging should be used.
     */
    public static function shouldLog(): bool
    {
        return self::$shouldLog;
    }

    /**
     * Set whether to use cache.
     */
    public static function enableCaching(?bool $useCache = true): void
    {
        self::$useCache = $useCache;
    }

    /**
     * Disable caching.
     */
    public static function disableCaching(): void
    {
        self::$useCache = false;
    }

    /**
     * Clear the cache.
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
     */
    public function setCacheHandler(Cache $cache): self
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Get indicates if caching should be used.
     */
    public static function shouldCache(): bool
    {
        return self::$useCache;
    }
}
