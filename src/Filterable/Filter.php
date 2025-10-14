<?php

namespace Filterable;

use Filterable\Concerns\HandlesFilterables;
use Filterable\Concerns\HandlesFilterPermissions;
use Filterable\Concerns\HandlesPreFilters;
use Filterable\Concerns\HandlesRateLimiting;
use Filterable\Concerns\HandlesUserScope;
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
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Validation\ValidationException;
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

    /**
     * The Eloquent builder instance.
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

        // Set up the dependencies
        if ($cache) {
            $this->setCacheHandler($cache);
            $this->enableFeature('caching');
        }

        if ($logger) {
            $this->setLogger($logger);
            $this->enableFeature('logging');
        }
    }

    /**
     * Apply the filters.
     */
    public function apply(Builder $builder, ?array $options = []): Builder
    {
        // Ensure we're in a clean state or throw meaningful error
        if ($this->state !== 'initialized' && $this->state !== 'failed') {
            throw new RuntimeException("Filter cannot be reapplied. Current state: {$this->state}");
        }

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
                'filters' => $this->getFilterables(),
                'options' => $this->options,
            ]);
        }

        // Apply query optimizations if enabled
        if ($this->hasFeature('optimization')) {
            $this->optimizeQuery();
        }

        try {
            // Security checks (only if the features are enabled)
            if ($this->hasFeature('validation')) {
                $this->validateFilterInputs();
            }

            if ($this->hasFeature('permissions')) {
                $this->checkFilterPermissions();
            }

            if ($this->hasFeature('rateLimit')) {
                if (! $this->checkRateLimits()) {
                    throw new RuntimeException('Filter request exceeded rate limit or complexity threshold');
                }
            }

            // Apply value transformations if enabled
            if ($this->hasFeature('valueTransformation')) {
                $this->transformFilterValues();
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

            Event::dispatch(new FilterApplied($this, $this->builder, $this->getCurrentFilters()));

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

            Event::dispatch(new FilterFailed($this, $this->builder, $e));

            // Always rethrow validation exceptions
            if ($e instanceof ValidationException) {
                throw $e;
            }

            // For other exceptions, let subclasses decide whether to rethrow
            $this->handleFilteringException($e);
        }

        // End performance monitoring if enabled
        if ($this->hasFeature('performance')) {
            $this->endTiming();
        }

        return $this->builder;
    }

    /**
     * Execute the query and get the results.
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
        return $this->getBuilder()->get();
    }

    /**
     * Run the full filter pipeline and get results in one step.
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
            $debugInfo['sql'] = $this->getBuilder()->toSql();
            $debugInfo['bindings'] = $this->getBuilder()->getBindings();
        }

        // Add performance metrics if available
        if ($this->hasFeature('performance')) {
            $debugInfo['metrics'] = $this->getMetrics();
        }

        return $debugInfo;
    }

    /**
     * Get the Eloquent builder instance.
     */
    public function getBuilder(): ?Builder
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
        $this->currentFilters = [];
        $this->customFilters = [];

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

        if (isset($defaults['features']) && is_array($defaults['features'])) {
            $featureDefaults = array_intersect_key($defaults['features'], $this->features);
            $this->features = array_merge($this->features, $featureDefaults);
        }

        if (isset($defaults['options']) && is_array($defaults['options'])) {
            foreach ($defaults['options'] as $key => $value) {
                $this->options[$key] = $value;
            }
        }

        $configuredTtl = $defaults['cache']['ttl'] ?? null;

        if (is_numeric($configuredTtl)) {
            $this->cacheExpiration = (int) $configuredTtl;
        }
    }
}
