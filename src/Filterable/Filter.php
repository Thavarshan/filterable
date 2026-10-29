<?php

namespace Filterable;

use Filterable\Concerns\HandlesFilterables;
use Filterable\Concerns\HandlesFilterPermissions;
use Filterable\Concerns\HandlesPreFilters;
use Filterable\Concerns\HandlesRateLimiting;
use Filterable\Concerns\HandlesUserScope;
use Filterable\Concerns\HasFilterDefinitions;
use Filterable\Concerns\InteractsWithCache;
use Filterable\Concerns\InteractsWithLogging;
use Filterable\Concerns\ManagesMemory;
use Filterable\Concerns\MonitorsPerformance;
use Filterable\Concerns\OptimizesQueries;
use Filterable\Concerns\SmartCaching;
use Filterable\Concerns\SupportsFilterChaining;
use Filterable\Concerns\TransformsFilterValues;
use Filterable\Concerns\ValidatesFilterInput;
use Filterable\Contracts\Filter as FilterContract;
use Filterable\Events\FilterApplied;
use Filterable\Events\FilterApplying;
use Filterable\Events\FilterFailed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

abstract class Filter implements FilterContract
{
    use Conditionable;
    use HandlesFilterables,
        HandlesFilterPermissions,
        HandlesPreFilters,
        HandlesRateLimiting,
        HandlesUserScope,
        InteractsWithCache,
        InteractsWithLogging,
        ManagesMemory,
        MonitorsPerformance,
        OptimizesQueries,
        SmartCaching,
        SupportsFilterChaining,
        TransformsFilterValues,
        ValidatesFilterInput;
    use HasFilterDefinitions;

    /**
     * The Eloquent builder instance.
     *
     * @var \Illuminate\Database\Eloquent\Builder<*>|null
     */
    protected ?Builder $builder = null;

    /**
     * Extra options for the filter.
     *
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * Features that are enabled for this filter.
     *
     * @var array<string, bool>
     */
    protected array $features = [
        'validation' => false,
        'permissions' => false,
        'rateLimit' => false,
        'caching' => false,
        'logging' => false,
        'performance' => false,
        'optimization' => false,
        'memoryManagement' => false,
        'filterChaining' => false,
        'valueTransformation' => false,
    ];

    /**
     * The current state of the filter.
     */
    protected string $state = 'initialized';

    /**
     * Last exception encountered during filtering.
     */
    protected ?Throwable $lastException = null;

    /**
     * Create a new filter instance.
     *
     * @return void
     */
    public function __construct(
        protected Request $request,
        ?Cache $cache = null,
        ?LoggerInterface $logger = null
    ) {
        $this->applyConfigurationDefaults();
        $this->applyDefinitionDefaults();

        // Set up the dependencies
        if ($cache) {
            $this->setCacheHandler($cache);
        }

        if ($logger) {
            $this->setLogger($logger);
            $this->enableFeature('logging');
        }
    }

    /**
     * Apply the filters.
     *
     * @param \Illuminate\Database\Eloquent\Builder<*> $builder
     * @param  array<string, mixed>|null  $options
     * @return \Illuminate\Database\Eloquent\Builder<*>
     */
    public function apply(Builder $builder, ?array $options = []): Builder
    {
        // Ensure we're in a clean state or throw meaningful error
        if ($this->state !== 'initialized' && $this->state !== 'failed') {
            throw new RuntimeException("Filter cannot be reapplied. Current state: {$this->state}");
        }

        $this->validateFilterDefinitions();
        $this->getFilterables();
        $this->builder = $builder;
        $this->options = array_merge($this->options, $options ?? []);
        $this->state = 'applying';

        Event::dispatch(new FilterApplying($this, $this->builder, $this->options));

        // Start performance monitoring if enabled
        if ($this->hasFeature('performance')) {
            $this->startTiming();
        }

        // Log start of filter application if logging is enabled
        if ($this->hasFeature('logging')) {
            $this->logInfo('Beginning filter application', [
                'options' => $this->options,
            ]);
        }

        try {
            // Security checks (only if the features are enabled)
            if ($this->hasFeature('permissions')) {
                $this->checkFilterPermissions();
            }

            if ($this->hasFeature('validation')) {
                $this->validateFilterInputs();
            }

            // Apply value transformations if enabled
            if ($this->hasFeature('valueTransformation')) {
                $this->transformFilterValues();
            }

            if ($this->hasFeature('rateLimit') && ! $this->checkRateLimits()) {
                throw new AuthorizationException('Filter request exceeded rate limit or complexity threshold');
            }

            if ($this->hasFeature('optimization')) {
                $this->optimizeQuery();
            }

            // Core filtering (always applied)
            $this->applyUserScope();
            $this->applyPreFilters();
            $this->applyFilterables();

            // Apply custom filter chains if enabled
            if ($this->hasFeature('filterChaining') && ! empty($this->customFilters)) {
                $this->applyCustomFilters();
            }

            $this->state = 'applied';

            Event::dispatch(new FilterApplied($this, $this->resolveBuilder(), $this->getCurrentFilters()));

            // Log completion of filter application if logging is enabled
            $this->logInfo('Filter application completed', [
                'applied_filters' => $this->getCurrentFilters(),
            ]);
        } catch (Throwable $e) {
            $this->state = 'failed';
            $this->lastException = $e;

            // Log error if logging is enabled
            $this->logWarning('Error applying filters', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Event::dispatch(new FilterFailed($this, $this->resolveBuilder(), $e));

            // Always rethrow validation exceptions
            if ($e instanceof ValidationException || $e instanceof AuthorizationException || $e instanceof InvalidArgumentException) {
                throw $e;
            }

            // For other exceptions, let subclasses decide whether to rethrow
            $this->handleFilteringException($e);
        }

        // End performance monitoring if enabled
        if ($this->hasFeature('performance')) {
            $this->endTiming();
        }

        return $this->resolveBuilder();
    }

    /**
     * Execute the query and get the results.
     *
     * @return \Illuminate\Support\Collection<*, *>
     */
    public function get(): Collection
    {
        if ($this->state === 'initialized') {
            throw new RuntimeException('You must call apply() before get()');
        }

        if ($this->state === 'failed') {
            throw new RuntimeException(
                'Filters failed to apply: '.$this->lastException?->getMessage(),
                0,
                $this->lastException
            );
        }

        // Use cached query execution if caching is enabled
        if ($this->hasFeature('caching')) {
            return $this->executeQueryWithCaching();
        }

        // Use memory-efficient processing for large datasets if enabled
        if ($this->hasFeature('memoryManagement') && ($this->options['chunk_size'] ?? false)) {
            return $this->executeQueryWithMemoryManagement();
        }

        // Otherwise, just execute the query
        return $this->resolveBuilder()->get();
    }

    /**
     * Run the full filter pipeline and get results in one step.
     *
     * @param \Illuminate\Database\Eloquent\Builder<*> $builder
     * @param  array<string, mixed>|null  $options
     * @return \Illuminate\Support\Collection<*, *>
     */
    public function runQuery(Builder $builder, ?array $options = []): Collection
    {
        $this->apply($builder, $options);

        return $this->get();
    }

    /**
     * Enable a specific feature.
     */
    public function enableFeature(string $feature): self
    {
        if (array_key_exists($feature, $this->features)) {
            $this->features[$feature] = true;
        }

        return $this;
    }

    /**
     * Enable multiple features at once.
     *
     * @param  list<string>  $features
     */
    public function enableFeatures(array $features): self
    {
        foreach ($features as $feature) {
            $this->enableFeature($feature);
        }

        return $this;
    }

    /**
     * Disable a specific feature.
     */
    public function disableFeature(string $feature): self
    {
        if (array_key_exists($feature, $this->features)) {
            $this->features[$feature] = false;
        }

        return $this;
    }

    /**
     * Check if a feature is enabled.
     */
    public function hasFeature(string $feature): bool
    {
        return $this->features[$feature] ?? false;
    }

    /**
     * Get execution statistics and query info for debugging.
     *
     * @return array<mixed>
     */
    public function getDebugInfo(): array
    {
        $debugInfo = [
            'state' => $this->state,
            'filters_applied' => $this->getCurrentFilters(),
            'features_enabled' => array_filter($this->features),
            'options' => $this->options,
        ];

        // Add SQL info if we have a builder
        if ($this->builder) {
            $debugInfo['sql'] = $this->resolveBuilder()->toSql();
            $debugInfo['bindings'] = $this->resolveBuilder()->getBindings();
        }

        // Add performance metrics if available
        if ($this->hasFeature('performance')) {
            $debugInfo['metrics'] = $this->getMetrics();
        }

        return $debugInfo;
    }

    /**
     * Get the Eloquent builder instance.
     *
     * @return \Illuminate\Database\Eloquent\Builder<*>|null
     */
    public function getBuilder(): ?Builder
    {
        return $this->builder;
    }

    /**
     * Set the Eloquent builder instance.
     *
     * @param \Illuminate\Database\Eloquent\Builder<*> $builder
     */
    public function setBuilder(Builder $builder): self
    {
        $this->builder = $builder;

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
     * Set a specific option value.
     */
    public function setOption(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Set the extra options for the filter.
     *
     * @param  array<string, mixed>  $options
     */
    public function setOptions(array $options): self
    {
        foreach ($options as $key => $value) {
            $this->setOption($key, $value);
        }

        return $this;
    }

    /**
     * Reset the filter to its initial state.
     */
    public function reset(): self
    {
        $this->state = 'initialized';
        $this->builder = null;
        $this->lastException = null;
        $this->filterables = [];
        $this->rawFilterables = [];
        $this->inputsResolved = false;
        $this->currentFilters = [];
        $this->customFilters = [];
        $this->forUser = null;
        $this->authorizationActor = null;
        $this->actorSet = false;

        return $this;
    }

    /**
     * Transform filter values if transformers are registered.
     * This method is called before applying filters if valueTransformation is enabled.
     */
    protected function transformFilterValues(): void
    {
        if (empty($this->transformers)) {
            return;
        }

        $filterables = $this->getFilterables();

        foreach ($filterables as $filter => $value) {
            if (isset($this->transformers[$filter])) {
                $this->filterables[$filter] = $this->transformFilterValue($filter, $value);
            }
        }
    }

    /**
     * Handle exceptions that occur during filtering.
     * Subclasses can override this method to customize exception handling.
     */
    protected function handleFilteringException(Throwable $exception): void
    {
        // By default, don't rethrow the exception
        // Subclasses can override this to change behavior
    }

    /**
     * Apply configuration-driven defaults to the filter instance.
     */
    protected function applyConfigurationDefaults(): void
    {
        $defaults = config('filterable.defaults', []);
        if (! is_array($defaults)) {
            throw new InvalidArgumentException('Filterable defaults must be an array.');
        }
        $features = $defaults['features'] ?? [];
        if (! is_array($features)) {
            throw new InvalidArgumentException('Filterable feature defaults must be an array.');
        }
        foreach ($features as $feature => $enabled) {
            if (! is_string($feature) || ! is_bool($enabled) || ! array_key_exists($feature, $this->features)) {
                throw new InvalidArgumentException('Invalid Filterable feature default.');
            }
            $this->features[$feature] = $enabled;
        }
        $options = $defaults['options'] ?? [];
        if (! is_array($options)) {
            throw new InvalidArgumentException('Filterable option defaults must be an array.');
        }
        foreach ($options as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Filterable option keys must be strings.');
            }
            $this->options[$key] = $value;
        }
        $cache = $defaults['cache'] ?? [];
        if (! is_array($cache)) {
            throw new InvalidArgumentException('Filterable cache defaults must be an array.');
        }
        $ttl = $cache['ttl'] ?? null;
        if ($ttl !== null) {
            if (! is_numeric($ttl) || (int) $ttl < 0) {
                throw new InvalidArgumentException('Cache TTL must be zero or greater.');
            }
            $this->cacheExpiration = (int) $ttl;
        }
    }
}
