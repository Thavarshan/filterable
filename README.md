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

## Installation & Setup

```bash
composer require jerome/filterable
```

Package auto-discovery registers the `FilterableServiceProvider`, which contextual-binds the current `Request` into resolved filters and exposes the `make:filter` Artisan command. Publish the configuration to set global feature defaults, cache behaviour, or runtime options:

```bash
php artisan vendor:publish --tag=filterable-config
```

Stubs live under `src/Filterable/Console/stubs/` and can be overridden by placing copies in your application's `stubs` directory.

## Highlights

- Publishable configuration (`config/filterable.php`) to set default feature bundles, runtime options, and cache TTLs that the base filter reads during construction.
- Stateful lifecycle with `apply`, `get`, `runQuery`, `reset`, rich debug output via `getDebugInfo()`, lifecycle events (`FilterApplying`, `FilterApplied`, `FilterFailed`), and configurable exception handling.
- Opt-in concerns for validation, permissions, rate limiting, caching (with heuristics), logging, performance metrics, query optimisation, memory management, value transformation, and fluent filter chaining.
- Drop-in `Filterable` Eloquent scope trait so any model can accept a filter instance.
- Smart caching that builds deterministic cache keys, supports tags, memoises counts, and can decide automatically when to cache complex queries.
- Contextual binding in `FilterableServiceProvider` makes sure container-resolved filters receive the current HTTP `Request`; injecting a cache repository or PSR-3 logger auto-enables the relevant features.
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

## Lifecycle & Core API

- `apply(Builder $builder, ?array $options = [])` binds the filter to a query, merges options, runs enabled concerns, and transitions the state from `initialized` → `applying` → `applied`. Re-applying without `reset()` raises a `RuntimeException`.
- `get()` returns an `Illuminate\Support\Collection` of results, delegating to caching or memory-managed helpers when those features are active. `runQuery()` is a convenience wrapper for `apply()` + `get()`.
- `count()` respects smart caching (including tagged caches and memoised counts when enabled). `toSql()` exposes the raw SQL for debugging.
- `enableFeature()`, `enableFeatures()`, `disableFeature()`, `hasFeature()` toggle concerns per instance; defaults may be set in `config/filterable.php` and are applied in the constructor.
- `setOption()`, `setOptions()` persist runtime flags (for example `chunk_size`, `use_chunking`) that concerns such as `OptimizesQueries` and `ManagesMemory` consume.
- `reset()` returns the filter to the `initialized` state so it can be applied again. `getDebugInfo()` surfaces state, filters applied, options, SQL/bindings, and metrics.

## Feature Guides & API

### Validation & Value Transformation

- Enable with `enableFeature('validation')` and configure with `setValidationRules()`, `addValidationRule()`, and `setValidationMessages()`. Only active filters are validated and `ValidationException` is rethrown.
- Enable `valueTransformation` to normalise inputs before filter methods execute. Register per-key transformers with `registerTransformer()` or bulk-array transforms with `transformArray()`.

```php
$filter->enableFeatures(['validation', 'valueTransformation'])
    ->setValidationRules([
        'status' => ['nullable', Rule::in(['draft', 'published'])],
        'tags' => ['array'],
    ])
    ->registerTransformer('tags', fn ($value) => array_map('intval', (array) $value));
```

### Permissions & User Scope

- `forUser($user)` scopes queries to the authenticated identifier and folds that identifier into cache keys automatically.
- Enable `permissions` and declare requirements with `setFilterPermissions()`. Override `userHasPermission()` in your filter to plug into your authorisation layer; disallowed filters are dropped (and optionally logged) before execution.

```php
$filter->enableFeature('permissions')
    ->forUser($request->user())
    ->setFilterPermissions(['email' => 'view-sensitive-fields']);
```

### Rate Limiting

- Enable with `enableFeature('rateLimit')`. Defaults allow 10 filters, a complexity budget of 100, and 60 attempts within a 60-second window; decay is `ceil(complexity/10)` seconds.
- Tune guardrails with `setMaxFilters()`, `setMaxComplexity()`, and `setFilterComplexity()` (array-valued filters multiply complexity). Override `resolveRateLimitMaxAttempts()`, `resolveRateLimitWindowSeconds()`, or `resolveRateLimitDecaySeconds()` for finer control.

```php
$filter->enableFeature('rateLimit')
    ->setMaxFilters(5)
    ->setMaxComplexity(25)
    ->setFilterComplexity(['tags' => 3, 'q' => 2]);
```

### Caching & SmartCaching

- Inject an `Illuminate\Contracts\Cache\Repository` or call `enableFeature('caching')` to activate caching. TTL defaults to 5 minutes or `config('filterable.defaults.cache.ttl')`; override per instance with `setCacheExpiration()`.
- Opt into result or count caching via `cacheResults()` / `cacheCount()`, and scope invalidation with `cacheTags()`, `clearCache()`, and `clearRelatedCaches()`. Cache keys include sanitised filter values and optional user identifiers from `forUser()`.
- `SmartCaching` will automatically cache more complex queries (multiple where clauses, joins, select statements) when `caching` is enabled, while skipping trivial single-clause lookups.

```php
$filter->enableFeature('caching')
    ->cacheTags(['posts'])
    ->cacheResults()
    ->cacheCount()
    ->setCacheExpiration(15);

$posts = Post::query()->filter($filter)->get();
$total = $filter->count();
```

### Logging & Performance Metrics

- Inject a PSR-3 logger or call `setLogger()` + `enableFeature('logging')` to emit structured lifecycle logs. Hooks such as `applyFilterable` and cache-building log automatically when logging is active.
- Enable `performance` to measure execution time, memory usage, and filter count; extend with `addMetric()` and read via `getMetrics()` / `getExecutionTime()`.

### Query Optimisation & Filter Chaining

- Enable `optimization` to apply `select()`, `with()`, and `chunkSize()` before filters run; `useIndex()` can hint MySQL indexes when appropriate.
- Enable `filterChaining` to queue fluent additions after request-driven filters: `where()`, `whereIn()`, `whereNotIn()`, `whereBetween()`, and `orderBy()` are supported.

```php
$filter->enableFeatures(['optimization', 'filterChaining'])
    ->select(['id', 'title', 'status'])
    ->with(['author', 'tags'])
    ->chunkSize(500)
    ->where('status', 'published')
    ->orderBy('published_at', 'desc');
```

### Memory Management

- Enable `memoryManagement` for streaming helpers that avoid loading whole result sets into memory: `lazy()`, `lazyEach()`, `cursor()`, `stream()`, `streamGenerator()`, `chunk()`, `map()`, `filter()`, `reduce()`.
- `executeQueryWithMemoryManagement()` underpins `get()` when `chunk_size` is set; `resolveChunkSize()` honours `chunk_size` options or provided arguments. Call `apply()` before streaming helpers; misuse raises a `RuntimeException`.

```php
$filter->enableFeature('memoryManagement')
    ->setOption('chunk_size', 250);

$filter->apply(Post::query());
$filter->lazyEach(fn ($post) => /* ... */, 250);
```

### Pre-Filters & Manual Filters

- Register global constraints with `registerPreFilters()`; they run before request-driven filters and are logged when logging is enabled.
- Add programmatic filter values with `appendFilterable()`, or alias request keys to method names via `protected array $filterMethodMap` on your filter class. `asCollectionFilter()` returns a callable compatible with collection pipelines when you want to reuse filterables outside of Eloquent.

### Debugging & Events

- `getDebugInfo()` returns state, enabled features, options, SQL, bindings, and (when `performance` is enabled) metrics. Override `handleFilteringException()` to decide whether to swallow or rethrow non-validation errors.
- Listen for `FilterApplying`, `FilterApplied`, and `FilterFailed` events around `apply()` to hook telemetry, notifications, or side effects.

## Configuration

The publishable `config/filterable.php` controls defaults applied during filter construction:

```php
return [
    'defaults' => [
        'features' => [
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
        ],
        'options' => [/* runtime options seeded here */],
        'cache' => ['ttl' => null],
    ],
];
```

Per-filter overrides always win—call `enableFeature()`, `disableFeature()`, `setOption()`, or `setCacheExpiration()` inside individual filters when you need different defaults.

## Artisan Generator & Stubs

`php artisan make:filter` scaffolds a filter class under `App\Filters` by default:

- `--basic` emits a minimal filter without feature toggles.
- `--model=User` imports the model and pre-fills a typed constructor parameter.
- `--force` overwrites an existing class.

Publish customised stubs by copying `src/Filterable/Console/stubs/` into your application's `stubs/` directory; the command prefers application stubs when present.

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
