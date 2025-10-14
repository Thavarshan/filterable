[![Filterable](./assets/Banner.jpg)](https://github.com/Thavarshan/filterable)

# About Filterable

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jerome/filterable.svg)](https://packagist.org/packages/jerome/filterable)
[![Tests](https://github.com/Thavarshan/filterable/actions/workflows/tests.yml/badge.svg?label=tests&branch=main)](https://github.com/Thavarshan/filterable/actions/workflows/tests.yml)
[![Lint](https://github.com/Thavarshan/filterable/actions/workflows/lint.yml/badge.svg)](https://github.com/Thavarshan/filterable/actions/workflows/lint.yml)
[![CodeQL](https://github.com/Thavarshan/filterable/actions/workflows/github-code-scanning/codeql/badge.svg)](https://github.com/Thavarshan/filterable/actions/workflows/github-code-scanning/codeql)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](https://phpstan.org/)
[![PHP Version](https://img.shields.io/packagist/php-v/jerome/filterable.svg)](https://packagist.org/packages/jerome/filterable)
[![License](https://img.shields.io/packagist/l/jerome/filterable.svg)](https://packagist.org/packages/jerome/filterable)
[![Total Downloads](https://img.shields.io/packagist/dt/jerome/filterable.svg)](https://packagist.org/packages/jerome/filterable)
[![GitHub Stars](https://img.shields.io/github/stars/Thavarshan/filterable.svg?style=social&label=Stars)](https://github.com/Thavarshan/filterable/stargazers)

Filterable is a Laravel package for turning HTTP request parameters into rich, composable Eloquent query filters. The base `Filter` class exposes a stateful pipeline that you can extend, toggle, and compose with traits to add validation, caching, logging, rate limiting, memory management, and more. Everything is opt-in, so you enable only the behaviour you need while keeping type-safe, testable filters.

## Requirements

- PHP 8.3 or 8.4
- Laravel 11.x or 12.x components (`illuminate/cache`, `illuminate/database`, `illuminate/http`, `illuminate/support`)
- A configured cache store when you enable caching features
- A PSR-3 logger when you enable logging (optional)

## Installation

```bash
composer require jerome/filterable
```

Package auto-discovery registers the `FilterableServiceProvider`, which in turn exposes the `make:filter` Artisan command. Publish the configuration when you want to tweak the default feature flags or cache behaviour:

```bash
php artisan vendor:publish --tag=filterable-config
```

Stubs live under `src/Filterable/Console/stubs/` and can be overridden by placing copies in your application's `stubs` directory.

## Highlights

- Publishable configuration (`config/filterable.php`) to set default feature bundles, runtime options, and cache TTLs that the base filter reads during construction.
- Stateful lifecycle with `apply`, `get`, `runQuery`, `reset`, rich debug output via `getDebugInfo()`, and lifecycle events (`FilterApplying`, `FilterApplied`, `FilterFailed`).
- Opt-in concerns for validation, permissions, rate limiting, caching (with heuristics), logging, performance metrics, query optimisation, memory management, value transformation, and fluent filter chaining.
- Drop-in `Filterable` Eloquent scope trait so any model can accept a filter instance.
- Smart caching that builds deterministic cache keys, supports tags, memoises counts, and can decide automatically when to cache complex queries.
- Memory-friendly helpers (`lazy`, `stream`, `streamGenerator`, `lazyEach`, `cursor`, `chunk`, `map`, `filter`, `reduce`) when the `memoryManagement` feature is enabled.
- First-party Artisan generator with `--basic`, `--model`, and `--force` options to rapidly scaffold filters.

## Repository Layout

- `src/Filterable/Filter.php` – abstract base class orchestrating the filter lifecycle and feature toggles.
- `src/Filterable/Concerns/` – traits implementing discrete behaviour (filter discovery, validation, caching, logging, performance, optimisation, rate limiting, etc.).
- `src/Filterable/Contracts/` – interfaces for the filter pipeline and the Eloquent scope signature.
- `src/Filterable/Traits/Filterable.php` – model scope that forwards to a `Filter` instance.
- `src/Filterable/Console/MakeFilterCommand.php` & `src/Filterable/Console/stubs/` – Artisan generator and overrideable stub templates.
- `src/Filterable/Providers/FilterableServiceProvider.php` – registers the package and console command via `spatie/laravel-package-tools`.
- `bin/` – executable scripts executed by the Composer `lint`, `fix`, and `test` commands.
- `tests/` – Orchestra Testbench suite with concern-focused tests and reusable fixtures in `tests/Fixtures/`.
- `assets/` – shared media used in documentation.
- `config/filterable.php` – publishable defaults for feature toggles, cache TTL, and runtime options.
- `database/factories/` – reserved for additional factories should you extend the package.

## Quick Start

### 1. Generate a filter

```bash
php artisan make:filter PostFilter --model=Post
```

`--model` wires the stub to your Eloquent model. Use `--basic` for an empty shell or `--force` to overwrite an existing class.

### 2. Implement filtering logic

```php
<?php

namespace App\Filters;

use Filterable\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class PostFilter extends Filter
{
    /**
     * Request keys that map straight to filter methods.
     *
     * Methods follow camelCased versions of the keys (e.g. published_after → publishedAfter).
     */
    protected array $filters = ['status', 'published_after', 'q'];

    public function __construct(Request $request)
    {
        parent::__construct($request);

        $this->enableFeatures([
            'validation',
            'optimization',
            'filterChaining',
            'valueTransformation',
        ]);

        $this->setValidationRules([
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'published_after' => ['nullable', 'date'],
        ]);

        $this->registerTransformer('published_after', fn ($value) => Carbon::parse($value));
        $this->registerPreFilters(fn (Builder $query) => $query->where('is_visible', true));
        $this->select(['id', 'title', 'status', 'published_at'])->with('author');
    }

    protected function status(string $value): void
    {
        $this->getBuilder()->where('status', $value);
    }

    protected function publishedAfter(Carbon $date): void
    {
        $this->getBuilder()->whereDate('published_at', '>=', $date);
    }

    protected function q(string $term): void
    {
        $this->getBuilder()->where(function (Builder $query) use ($term) {
            $query->where('title', 'like', "%{$term}%")
                ->orWhere('body', 'like', "%{$term}%");
        });
    }
}
```

Define `protected array $filterMethodMap` when you need to alias request keys to method names. Programmatic filters can be appended with `appendFilterable('key', $value)` before `apply()` runs. Supplying an `Illuminate\Contracts\Cache\Repository` or `Psr\Log\LoggerInterface` to the constructor immediately enables the `caching` and `logging` features.

### 3. Attach the scope to a model

```php
<?php

namespace App\Models;

use Filterable\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use Filterable;
}
```

### 4. Run the filter pipeline

```php
<?php

namespace App\Http\Controllers;

use App\Filters\PostFilter;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController
{
    public function index(Request $request, PostFilter $filter)
    {
        $posts = Post::query()
            ->filter(
                $filter
                    ->forUser($request->user())
                    ->enableFeature('caching')
                    ->setOptions(['chunk_size' => 500])
            )
            ->get();

        return PostResource::collection($posts);
    }
}
```

`apply()` may only be called once per instance; call `reset()` if you need to reuse a filter. Because the `Filter` base class uses Laravel's `Conditionable` trait, you can use helpers such as `$filter->when($request->boolean('validate'), fn ($filter) => $filter->enableFeature('validation'));`.

## Lifecycle & Runtime API

- `apply(Builder $builder, ?array $options = [])` – binds the filter to a query, merges options, runs enabled concerns, and transitions the state from `initialized` to `applied`. Re-applying without `reset()` raises a `RuntimeException`.
- `get()` – returns an `Illuminate\Support\Collection` of results, delegating to caching or memory-management helpers when their features are active.
- `runQuery(Builder $builder, ?array $options = [])` – convenience wrapper for `apply()` followed by `get()`.
- `count()` – respects smart caching, including tagged caches and memoised counts when enabled.
- `lazy()`, `stream()`, `streamGenerator()`, `lazyEach()`, `cursor()`, `chunk()`, `map()`, `filter()`, `reduce()` – memory-safe helpers activated by the `memoryManagement` feature.
- `enableFeature()`, `enableFeatures()`, `disableFeature()`, `hasFeature()` – toggle optional concerns per instance.
- `setOption()`, `setOptions()` – persist runtime flags (for example `chunk_size`, `use_chunking`) that concerns such as `OptimizesQueries` and `ManagesMemory` consume.
- `setValidationRules()`, `addValidationRule()`, `setValidationMessages()` – configure input validation when `validation` is enabled.
- `setFilterPermissions()` and `userHasPermission()` – declare permissions required for filters; override `userHasPermission()` to integrate with your auth layer.
- `setMaxFilters()`, `setMaxComplexity()`, `setFilterComplexity()` – configure rate limiting when the `rateLimit` feature is active.
- `registerPreFilters()`, `appendFilterable()`, `registerTransformer()` – hook in global constraints, programmatic filters, and value normalisers.
- `forUser()` – scope the query to an authenticated user; the user identifier becomes part of the cache key automatically.
- `cacheTags()`, `cacheResults()`, `cacheCount()`, `clearCache()`, `clearRelatedCaches()` – advanced caching controls layered on top of `InteractsWithCache` and `SmartCaching`.
- `setCacheExpiration()` – adjust the cache TTL (default is 5 minutes).
- `setLogger()` / `getLogger()` – swap the PSR-3 logger used by the logging concern.
- `addMetric()`, `getMetrics()`, `getExecutionTime()` – capture and retrieve custom performance telemetry.
- `getDebugInfo()` – inspect the current state, applied filters, enabled features, options, SQL, bindings, and metrics; invaluable for testing and observability.
- Laravel events `FilterApplying`, `FilterApplied`, and `FilterFailed` fire automatically around `apply()` to integrate with listeners, jobs, or telemetry pipelines.
- `reset()` – return the filter to the `initialized` state so it can be applied again.

## Feature Toggle Reference

Injecting a cache repository or logger into the filter constructor automatically enables the `caching` and `logging` features; everything else defaults to `false` until you opt in.

| Feature key | Concern | Enable via | Highlights |
| --- | --- | --- | --- |
| `validation` | `ValidatesFilterInput` | `enableFeature('validation')` | Validates active filter values with Laravel's validator before they run. |
| `permissions` | `HandlesFilterPermissions` | `enableFeature('permissions')` + `setFilterPermissions()` | Drops disallowed filters by consulting `userHasPermission()`. |
| `rateLimit` | `HandlesRateLimiting` | `enableFeature('rateLimit')` | Calculates request complexity, enforces max filters, and throttles via `RateLimiter`. |
| `caching` | `InteractsWithCache` / `SmartCaching` | Inject cache or call `enableFeature('caching')` | Deterministic cache keys, heuristic caching for complex queries, optional tags, result/count memoisation, manual invalidation helpers. |
| `logging` | `InteractsWithLogging` | Inject logger or call `enableFeature('logging')` | Structured info/debug/warning logs throughout the lifecycle. |
| `performance` | `MonitorsPerformance` | `enableFeature('performance')` | Records execution time, memory usage, filter count, and custom metrics. |
| `optimization` | `OptimizesQueries` | `enableFeature('optimization')` | Applies `select()`, `with()`, index hints, and chunk settings before filters run. |
| `memoryManagement` | `ManagesMemory` | `enableFeature('memoryManagement')` | Exposes streaming helpers for large result sets. |
| `filterChaining` | `SupportsFilterChaining` | `enableFeature('filterChaining')` | Queue additional fluent `where*` clauses after request-driven filters. |
| `valueTransformation` | `TransformsFilterValues` | `enableFeature('valueTransformation')` | Normalise inputs (casting, parsing) before filter methods execute. |

## Concern Details

### HandlesFilterables (core)

Discovers eligible request keys from the `$filters` array and optional `$filterMethodMap`, normalises them, and invokes matching methods (camel-cased by default). Use `getFilterables()`, `getCurrentFilters()`, and `appendFilterable()` to inspect or modify the values, and `asCollectionFilter()` to reuse the filter list in collection pipelines.

### HandlesPreFilters (core)

Register closures with `registerPreFilters()` to apply global constraints (such as soft deletes or visibility flags) before request-driven filters execute. Logging hooks fire automatically when `logging` is enabled.

### HandlesUserScope (core)

Call `forUser($user)` to scope the query to the authenticated user's identifier. The identifier is embedded in cache keys to keep cached results user-specific.

### HandlesFilterPermissions (`permissions`)

Map filters to required abilities with `setFilterPermissions()`. Override `userHasPermission()` to integrate with your authorisation logic; disallowed filters are removed silently (and optionally logged).

### HandlesRateLimiting (`rateLimit`)

Protect your data layer by limiting both the number of filters and an overall complexity score. Configure limits via `setMaxFilters()`, `setMaxComplexity()`, and `setFilterComplexity()`. The trait throttles offending requests using Laravel's `RateLimiter`, automatically scoping the key by request IP, filter class, and (when available) the user identifier set via `forUser()`. Override the hookable methods `resolveRateLimitMaxAttempts()`, `resolveRateLimitWindowSeconds()`, and `resolveRateLimitDecaySeconds()` to tailor attempt counts or decay windows per filter.

### InteractsWithCache & SmartCaching (`caching`)

Manage deterministic cache keys that include sanitised filter values and optional user identifiers. Adjust TTL with `setCacheExpiration()` (defaults to 5 minutes). Enable cache tags via `cacheTags()`, flag results or counts for caching with `cacheResults()` / `cacheCount()`, and invalidate with `clearCache()` or `clearRelatedCaches()`. `SmartCaching` inspects the underlying query (joins, multiple clauses, select statements) to decide when to cache automatically.

### InteractsWithLogging (`logging`)

Inject a PSR-3 logger (or rely on container resolution) and use `logInfo()`, `logDebug()`, and `logWarning()` to emit structured events. The base filter logs lifecycle milestones and failures when the feature is active.

### MonitorsPerformance (`performance`)

Wraps filter execution with timing hooks. `startTiming()` and `endTiming()` capture execution duration, memory usage, and filter counts. Extend with `addMetric()` to ship domain-specific telemetry.

### OptimizesQueries (`optimization`)

Call `select()`, `with()`, `chunkSize()`, and `useIndex()` ahead of filter execution. Options persist on the filter and are honoured by downstream helpers such as `get()`, `lazy()`, and `chunk()`.

### ManagesMemory (`memoryManagement`)

Expose lazy, chunked, and streaming traversal of the query. Use `lazy()`, `stream()`, `streamGenerator()`, `lazyEach()`, `cursor()`, `chunk()`, `map()`, `filter()`, and `reduce()` to process large datasets without exhausting memory. `executeQueryWithMemoryManagement()` now streams results under the hood before materialising them, so even `get()` stays efficient when the feature is enabled.

### SupportsFilterChaining (`filterChaining`)

Queue ad-hoc fluent constraints after the request-driven filters with helpers like `where()`, `whereIn()`, `whereNotIn()`, `whereBetween()`, and `orderBy()`. Custom filters execute once the main pipeline completes.

### TransformsFilterValues (`valueTransformation`)

Normalise request data before filters run. Register closures per key with `registerTransformer()`, or use `transformArray()` to mutate lists in bulk—ideal for casting enums, parsing date ranges, or splitting CSV inputs.

### ValidatesFilterInput (`validation`)

Attach Laravel validation rules and custom messages with `setValidationRules()` and `setValidationMessages()`. Only the active filters are validated, and `ValidationException` bubbles up to the caller if inputs are invalid.

## Configuration

Filterable ships with a publishable configuration file that controls default feature flags, runtime options, and cache behaviour.

```bash
php artisan vendor:publish --tag=filterable-config
```

The generated `config/filterable.php` lets you:

- Enable or disable features globally (`defaults.features.validation`, etc.).
- Pre-populate runtime options consumed by `setOption()`/`setOptions()`.
- Override the default cache TTL applied by `InteractsWithCache`.

Per-filter overrides always win—call `enableFeature()`, `disableFeature()`, or `setCacheExpiration()` inside individual filters when you need different defaults.

## Artisan Generator & Stubs

`php artisan make:filter` scaffolds a filter class under `App\Filters` by default:

- `--basic` – emit a minimal filter without feature toggles.
- `--model=User` – import the model and prefill a typed constructor parameter.
- `--force` – overwrite existing files.

Publish customised stubs by copying the files from `src/Filterable/Console/stubs/` into your application's `stubs/` directory; the command prefers application stubs when present.

## Tooling & Scripts

Package maintenance scripts live in `bin/` and are surfaced through Composer:

```bash
composer lint            # Runs Tighten Duster lint mode + PHP syntax checks
composer fix             # Formats with Duster and writes a timestamped log
composer test            # Executes PHPUnit via bin/test.sh
```

`./bin/test.sh` accepts `--filter=ClassName`, `--test=tests/FeatureTest.php`, `--coverage`, and `--parallel`. `./bin/lint.sh --strict` exits non-zero when any issue is detected.

## Testing

The PHPUnit suite runs on Orchestra Testbench (`phpunit.xml.dist`). `tests/TestCase.php` provisions an in-memory sqlite schema (`mocks` table) and aliases factories under `tests/Fixtures/`. Each concern has a dedicated test file (for example `CachingTest.php`, `ManagesMemoryTest.php`) with partial mocks and fixtures such as `MockFilterable`, `MockFilterableFactory`, and `TestFilter`. End-to-end behaviour is exercised in `tests/Integration/`, which boots the full filter pipeline (feature defaults, caching, streaming, lifecycle events) against the in-memory database.

Run targeted subsets with:

```bash
./bin/test.sh --filter=SupportsFilterChainingTest
./bin/test.sh --test=tests/HandlesRateLimitingTest.php
```

Add new integration doubles under `tests/Fixtures/` to stay aligned with the existing autoloading.

## Debugging & Observability

Use `getDebugInfo()` to inspect the filter state, active features, options, SQL, bindings, and (when enabled) performance metrics—handy for log enrichment or admin APIs. Combine with the logging concern for structured lifecycle output, and with `addMetric()` to surface domain-specific counters. Caching helpers expose `clearCache()` and `clearRelatedCaches()` for cache busting hooks (model events, queue jobs, etc.).

Listen for the `FilterApplying`, `FilterApplied`, and `FilterFailed` events to trigger downstream telemetry, notifications, or side effects whenever a filter runs.

## Frontend Usage

Send filter parameters as query strings from your clients:

```ts
await fetch('/posts?status=active&category_id=2');
await fetch('/posts?tags[]=laravel&tags[]=performance&sort_by=created_at:desc');
```

## Contributing

Please review `AGENTS.md` for contributor expectations around structure, tooling, and workflow. When ready:

1. Fork the repository and create a feature branch (`git checkout -b feature/my-change`).
2. Run `composer lint` and `composer test` (or `./bin/test.sh --coverage`) before opening a PR.
3. Describe the capabilities touched, newly exposed options, and verification commands in the pull request body.

## License

This project is open-sourced under the MIT license. See `LICENSE` for the full text.

## Authors

- **Jerome Thayananthajothy** – [Thavarshan](https://github.com/Thavarshan)

See [contributors](https://github.com/Thavarshan/filterable/contributors) for the full list of collaborators.

## Acknowledgements

Inspired by the flexibility of [spatie/laravel-query-builder](https://github.com/spatie/laravel-query-builder) and Tighten's [duster](https://github.com/tighten/duster) tooling.
