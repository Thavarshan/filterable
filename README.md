[![Filterable](./assets/Banner.jpg)](https://github.com/Thavarshan/filterable)

# Filterable

[![Latest Version](https://img.shields.io/packagist/v/jerome/filterable.svg)](https://packagist.org/packages/jerome/filterable)
[![Tests](https://github.com/Thavarshan/filterable/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/Thavarshan/filterable/actions/workflows/tests.yml)
[![Lint](https://github.com/Thavarshan/filterable/actions/workflows/lint.yml/badge.svg)](https://github.com/Thavarshan/filterable/actions/workflows/lint.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](https://phpstan.org/)
[![PHP Version](https://img.shields.io/packagist/php-v/jerome/filterable.svg)](https://packagist.org/packages/jerome/filterable)
[![Downloads](https://img.shields.io/packagist/dt/jerome/filterable.svg)](https://packagist.org/packages/jerome/filterable)
[![License](https://img.shields.io/packagist/l/jerome/filterable.svg)](LICENSE)

Filterable turns HTTP request parameters into explicit, reusable Eloquent query filters. Declare handlers with attributes or existing properties, then opt into validation, transformations, permissions, caching, complexity limits, logging, optimization, streaming, and query chaining.

This README is the complete usage guide. Examples below use an application `Post` model with ordinary fields such as `title`, `body`, `status`, `price`, `is_available`, `published_at`, and `tenant_id`; adapt those names and relationships to your schema.

## Contents

- [Requirements and installation](#requirements-and-installation)
- [Quick start](#quick-start)
- [Registering filters: attributes, properties, and aliases](#registering-filters-attributes-properties-and-aliases)
- [Inputs, lifecycle, and reuse](#inputs-lifecycle-and-reuse)
- [Feature switches, defaults, and precedence](#feature-switches-defaults-and-precedence)
- [Validation and transformations](#validation-and-transformations)
- [Permissions, Gate, and row scoping](#permissions-gate-and-row-scoping)
- [Complexity limits and throttling](#complexity-limits-and-throttling)
- [Result and count caching](#result-and-count-caching)
- [Model filter attributes](#model-filter-attributes)
- [Scopes and query callbacks](#scopes-and-query-callbacks)
- [Prefilters and fluent query chains](#prefilters-and-fluent-query-chains)
- [Query optimization](#query-optimization)
- [Streaming and memory management](#streaming-and-memory-management)
- [Logging, metrics, events, and errors](#logging-metrics-events-and-errors)
- [Optional Laravel 13 vector search](#optional-laravel-13-vector-search)
- [Generators and application stubs](#generators-and-application-stubs)
- [API reference](#api-reference)
- [Upgrading from 2.x](#upgrading-from-2x)
- [Development and verification](#development-and-verification)

## Requirements and installation

| Capability | Requirements |
| --- | --- |
| Core filters, package attributes, existing properties/setters | PHP 8.3–8.5; Laravel 11, 12, or 13 |
| Model `#[UseFilter]` attribute (no trait) | Laravel 11 through 13 |
| Legacy model scope | Laravel 11–13 |
| Native attributed model scope | Laravel 12+ |
| Inline callable attribute arguments, pipe syntax, native NoDiscard warnings | PHP 8.5 |
| Optional vector recipe | Laravel 13 and PostgreSQL with pgvector; Laravel also supports MariaDB 11.7+ vector queries |
| Caching | A Laravel cache repository when the caching concern is enabled |

```bash
composer require jerome/filterable

# Optional: publish global defaults.
php artisan vendor:publish --tag=filterable-config
```

Laravel package discovery registers `FilterableServiceProvider` and `make:filter`. Container-resolved filters receive the current HTTP Request. Filters are mutable and transient: resolve a fresh instance for each request/job rather than binding them as singleton or scoped services.

## Quick start

Generate an attributed filter:

```bash
php artisan make:filter PostFilter --attributes --model=Post
```

Edit its handlers for your application's columns. A complete portable example:

```php
<?php

namespace App\Filters;

use Carbon\CarbonImmutable;
use Filterable\Attributes\AsFilter;
use Filterable\Attributes\FilterRules;
use Filterable\Attributes\TransformWith;
use Filterable\Filter;

class PostFilter extends Filter
{
    #[AsFilter('status'), FilterRules(['in:draft,published'])]
    protected function status(string $value): void
    {
        $this->getBuilder()->where('status', $value);
    }

    #[AsFilter('q'), FilterRules(['string', 'max:200'])]
    protected function search(string $value): void
    {
        $this->getBuilder()->where(function ($query) use ($value) {
            $query->where('title', 'like', "%{$value}%")
                ->orWhere('body', 'like', "%{$value}%");
        });
    }

    #[AsFilter('min_price'), FilterRules(['numeric', 'min:0']), TransformWith('floatval')]
    protected function minPrice(float $value): void
    {
        $this->getBuilder()->where('price', '>=', $value);
    }

    #[AsFilter('available'), FilterRules(['boolean']), TransformWith('boolval')]
    protected function available(bool $value): void
    {
        $this->getBuilder()->where('is_available', $value);
    }

    #[AsFilter('published_after'), FilterRules(['date']), TransformWith([self::class, 'parseDate'])]
    protected function publishedAfter(CarbonImmutable $value): void
    {
        $this->getBuilder()->whereDate('published_at', '>=', $value);
    }

    // This helper has no AsFilter attribute and cannot be selected by an HTTP key.
    public static function parseDate(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value);
    }
}
```

**Backward compatibility:** The existing `Filterable\Traits\Filterable` trait remains fully supported and is not deprecated. Version 3.2 adds an optional `#[UseFilter]` alternative; existing models and calls such as `Post::filter($filter)` require no changes. See [the trait examples](#scopes-and-query-callbacks) to keep using that approach.

Connect the filter to your model with `#[UseFilter]` (no trait required):

```php
<?php

namespace App\Models;

use App\Filters\PostFilter;
use Filterable\Attributes\UseFilter;
use Illuminate\Database\Eloquent\Model;

#[UseFilter(PostFilter::class)]
class Post extends Model
{
    //
}
```

Use the declared filter from your controller and return ordinary Eloquent results:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Post;

class PostController
{
    public function index()
    {
        return Post::query()->filter()->orderBy('id')->get();
    }
}
```

Example request:

```text
GET /posts?status=published&q=Laravel&min_price=0&available=1&published_after=2026-01-01
```

Manual construction and execution work too:

```php
use App\Filters\PostFilter;
use App\Models\Post;
use Illuminate\Http\Request;

$filter = new PostFilter(Request::create('/posts?status=published'));
$query = $filter->apply(Post::query()); // Adds predicates; executes no query.
$posts = $query->get();                // Ordinary Eloquent execution.

$another = new PostFilter(new Request(['min_price' => '0']));
$posts = $another->runQuery(Post::query()); // apply() followed by filter get().
```

## Registering filters: attributes, properties, and aliases

Use `AsFilter` to bind an external key to a handler, including snake-case keys and aliases:

```php
#[\Filterable\Attributes\AsFilter('published_after')]
protected function newerThan(string $value): void
{
    $this->getBuilder()->whereDate('published_at', '>=', $value);
}
```

The property API remains supported. The following class implements the same registration without attributes:

```php
class LegacyPostFilter extends \Filterable\Filter
{
    protected array $filters = ['status'];

    protected array $filterMethodMap = ['published_after' => 'newerThan'];

    protected function status(string $value): void
    {
        $this->getBuilder()->where('status', $value);
    }

    protected function newerThan(string $value): void
    {
        $this->getBuilder()->whereDate('published_at', '>=', $value);
    }
}
```

`$filters` converts keys to camelCase method names; `$filterMethodMap` declares explicit aliases. You may mix these declarations with `AsFilter` when a repeated key resolves to the same method. Conflicting mappings and duplicate attribute aliases fail clearly before query mutation.

Handlers must be public/protected, non-static, and accept one value. Additional optional parameters are permitted. Private handlers, a by-reference or variadic first parameter, and multiple required parameters are invalid. Every programmatically appended key must also be registered. Unregistered request parameters and unrelated helpers are not discovered as handlers.

Inherited and trait handlers retain their attributes. Overriding a method replaces its metadata: repeat `AsFilter` and the desired policy attributes on the overriding method. Only `AsFilter` methods receive method-attribute policies; legacy filters can continue to use their concern maps/setters.

```php
$registeredKeys = $filter->getFilters();       // Includes registered aliases.
$effectiveInputs = $filter->getFilterables(); // Captures the input snapshot on first read.
$appliedKeys = $filter->getCurrentFilters();  // Effective keys after application.

\Filterable\Definitions\DefinitionRegistry::clear(); // Tests/application reloads.
```

The registry shares immutable class metadata only. It never shares requests, builders, users, results, or instance-bound transformers.

## Inputs, lifecycle, and reuse

Input is captured once from the supplied Request on the first `getFilterables()` or `apply()` call. Programmatic inputs override request inputs:

```php
$filter = new PostFilter(new Request(['status' => 'draft', 'min_price' => '10']));
$filter->appendFilterable('status', 'published');
$filter->appendFilterable('min_price', 0);
$filter->apply(Post::query());

// status=published and min_price=0 are used, even when the Request disagrees.
$inputs = $filter->getFilterables();
```

Missing keys, null, empty strings, and empty arrays are omitted. Integer `0`, string `'0'`, and boolean `false` are preserved. For HTTP boolean validation, send `0`/`1` or actual JSON booleans; Laravel's `boolean` rule does not accept arbitrary strings such as `'false'`.

Application proceeds through:

1. Definition validation and the input snapshot.
2. Enabled permission checks, then raw-input validation.
3. Enabled value transformations, then normalized complexity/rate checks.
4. Enabled optimization, row scoping, prefilters, and registered handlers.
5. Enabled custom query chains and lifecycle completion.

Repeated getters do not reload raw request values or restore denied inputs. `apply()` executes no query, regardless of cache state. `get()`, `count()`, and streaming methods execute when called. `runQuery()` combines `apply()` and `get()`.

A successfully applied filter is one-shot until reset:

```php
$request = new Request(['status' => 'draft']);
$filter = new PostFilter($request);
$drafts = $filter->runQuery(Post::query());

$request->replace(['status' => 'published']);
$filter->reset();
$published = $filter->runQuery(Post::query());
```

`reset()` clears the builder, error, raw/effective inputs, applied keys, queued query chains, row-scope user, and authorization actor. It rereads the **same supplied Request** on the next application. Features, options, rules, transformers, prefilter registration, and cache settings persist. For another Request or worker job, construct/resolve a new filter. After a failure, reset before retrying against a fresh builder.

## Feature switches, defaults, and precedence

Ordinary filters start with all optional concerns disabled. Behavior-bearing attributes enable their concerns; explicit class feature defaults and runtime setters can disable them.

| Feature string | Purpose | Enabling attribute |
| --- | --- | --- |
| `validation` | Validate raw inputs | `FilterRules` |
| `valueTransformation` | Convert input values before handlers | `TransformWith` |
| `permissions` | Authorize restricted keys | `RequiresPermission` |
| `rateLimit` | Complexity/filter limits and throttling | `FilterLimits`, `FilterComplexity` |
| `caching` | Terminal result/count caching | `CachePolicy` |
| `logging` | Lifecycle/concern logs | `FilterFeatures` |
| `performance` | Measure application duration | `FilterFeatures` |
| `optimization` | Apply configured select/eager loading | `FilterFeatures` |
| `memoryManagement` | Chunked materialization through filter `get()` | `FilterFeatures` |
| `filterChaining` | Apply queued fluent query additions | `FilterFeatures` |

Runtime setters:

```php
$filter->enableFeatures(['validation', 'valueTransformation', 'optimization']);
$filter->enableFeature('logging');
$filter->disableFeature('caching');
$enabled = $filter->hasFeature('validation');
$filter->setOptions(['chunk_size' => 250]);
$filter->setOption('cache_context', ['tenant' => $tenantId, 'policy' => $policyRevision]);
```

Class defaults:

```php
use Filterable\Attributes\CachePolicy;
use Filterable\Attributes\FilterFeatures;
use Filterable\Attributes\FilterLimits;

#[CachePolicy(ttlMinutes: 5, results: true, count: true, tags: ['posts'])]
#[FilterLimits(maxFilters: 5, maxComplexity: 25)]
#[FilterFeatures(['performance' => true, 'logging' => false])]
class ReportingPostFilter extends PostFilter {}

$filter = app(ReportingPostFilter::class);
$filter->disableFeature('caching'); // Explicit runtime disable wins.
```

Global configuration after publishing `config/filterable.php`:

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
        'options' => ['chunk_size' => 250],
        'cache' => ['ttl' => 5], // Minutes; zero disables terminal caching.
    ],
];
```

| Precedence, lowest to highest | Effect |
| --- | --- |
| Existing protected properties | Initial defaults; historical global-config override behavior is preserved |
| Global configuration | Applied during construction |
| Inherited class attributes | Oldest ancestor first |
| Concrete class attributes | Replace inherited defaults |
| Same-class `FilterFeatures` | Explicit booleans override concern auto-enablement |
| Runtime setters/constructor code after `parent::__construct()` | Override resolved defaults, including false/zero |
| Options passed to `apply($builder, $options)` | Replace matching instance options |

Method attributes supply per-handler policies and enable their concerns unless resolved class feature defaults explicitly override them. Child concern attributes can enable a feature disabled by a parent. A child `CachePolicy`/`FilterLimits` supplies its whole constructor policy, including defaults for omitted arguments.

Injecting a cache repository **does not enable caching**. Injecting a logger into the constructor retains the existing automatic logging enablement. `setCacheHandler()` and `setLogger()` supply collaborators; pair setters with the desired feature switches. Invalid class features, negative TTL/limits/weights, and invalid definitions throw actionable exceptions.

## Validation and transformations

### Method attributes

```php
use Filterable\Attributes\AsFilter;
use Filterable\Attributes\FilterRules;
use Filterable\Attributes\TransformWith;

#[AsFilter('published_after')]
#[FilterRules(['date'], messages: ['published_after.date' => 'Supply a valid date.'])]
#[TransformWith(DateTransformer::class)]
protected function publishedAfter(\Carbon\CarbonImmutable $value): void
{
    $this->getBuilder()->whereDate('published_at', '>=', $value);
}

// Put this invokable class in its own application file.
class DateTransformer
{
    public function __invoke(string $value): \Carbon\CarbonImmutable
    {
        return \Carbon\CarbonImmutable::parse($value);
    }
}
```

Validation runs against raw inputs before transformation and query mutation. Rules apply to present effective keys; empty inputs omitted by the lifecycle are not a way to require an otherwise optional HTTP parameter. Validate globally required request fields in your FormRequest/controller. Invalid active values raise Laravel `ValidationException` and never reach a handler.

`FilterRules` accepts string/array rules, Laravel rule objects, and messages. For request-specific rule objects or permissions, implement `RuleFactory`:

```php
use Filterable\Contracts\RuleFactory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StatusRuleFactory implements RuleFactory
{
    public function __invoke(Request $request): array|string
    {
        $choices = $request->user()?->can('view-drafts')
            ? ['draft', 'published']
            : ['published'];

        return ['string', Rule::in($choices)];
    }
}

// On an AsFilter handler:
#[FilterRules(factory: StatusRuleFactory::class)]
```

Factories and invokable transformer classes resolve from Laravel's container for each filter instance. Constructor-inject your application services normally. Pure static method arrays and named functions also work: `TransformWith([DateValues::class, 'parse'])` or `TransformWith('intval')`. HTTP input never chooses an executable callable.

### Runtime/property API

```php
$filter->enableFeatures(['validation', 'valueTransformation']);
$filter->setValidationRules([
    'status' => ['in:draft,published'],
    'published_after' => ['date'],
]);
$filter->addValidationRule('min_price', ['numeric', 'min:0']);
$filter->setValidationMessages(['min_price.min' => 'Price cannot be negative.']);
$filter->registerTransformer('published_after', fn ($value) => \Carbon\CarbonImmutable::parse($value));
$filter->registerTransformer('min_price', fn ($value) => (float) $value);
```

Setters override the corresponding resolved metadata. A runtime closure may use the current instance/request; it is stored on that filter only. The protected `$validationRules`, `$validationMessages`, and `$transformers` maps remain extension points. Inside a subclass, `transformArray($values, $callback)` maps array values while preserving keys.

You can convert to enums/value objects in the same way, with validation before conversion:

```php
enum Visibility: string { case Public = 'public'; case Private = 'private'; }

// Register this on a filter with an explicit 'visibility' handler.
$filter->setValidationRules(['visibility' => ['in:public,private']]);
$filter->registerTransformer('visibility', fn ($value) => Visibility::from($value));
// The handler accepts Visibility and binds $value->value to the query.
```

### Optional PHP 8.5 callable arguments

Keep these definitions in PHP 8.5-only files or generated stubs:

```php
#[AsFilter('q')]
#[TransformWith(static function (string $value): string {
    return $value |> trim(...) |> strtolower(...);
})]
protected function search(string $value): void
{
    $this->getBuilder()->where('title', 'like', "%{$value}%");
}

// A first-class callable is another PHP 8.5 attribute option:
#[TransformWith(strtolower(...))]
```

Use a static anonymous function with no captured variables. A version check inside the same file cannot protect PHP 8.3/8.4 from unsupported syntax. Invokable classes, function-name strings, runtime closures, and static method arrays keep the existing runtime floor.

## Permissions, Gate, and row scoping

Package permission attributes explicitly consult Laravel Gate:

```php
#[\Filterable\Attributes\AsFilter('email')]
#[\Filterable\Attributes\RequiresPermission('view-sensitive-fields', reject: true)]
protected function email(string $value): void
{
    $this->getBuilder()->where('email', $value);
}
```

Register your application's ability and pass an authorization actor independently of row scoping:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('view-sensitive-fields', fn ($user) => $user->is_admin);

$filter->authorizeFor($request->user());
$filter->apply(User::query());
```

| Permission policy | Behavior |
| --- | --- |
| `RequiresPermission('ability')` | Require the ability; skip the key when denied |
| `RequiresPermission(['a', 'b'])` | Require all abilities |
| `RequiresPermission(['a', 'b'], any: true)` | Require any one ability |
| `reject: true` | Throw `AuthorizationException` instead of skipping |
| Guest actor | Declared requirements are denied |

Without `authorizeFor()`, the Request user is used, then the legacy `forUser()` user. Explicit `authorizeFor(null)` selects a guest. Skipped keys stay absent from effective inputs and applied-filter diagnostics. Authorization rejection cannot be swallowed by the generic exception hook. Use tenant/ownership predicates for row-level security; a skipped optional search key is not an ownership boundary. Laravel controller `#[Authorize]` attributes are not interpreted on arbitrary Filter methods.

Legacy permission maps remain available:

```php
$filter->enableFeature('permissions');
$filter->authorizeFor($request->user());
$filter->setFilterPermissions(['email' => 'view-sensitive-fields']);
```

The default `userHasPermission(string|array $permission)` hook now checks Gate; override it for another authorization system. No third-party permission package is required. Runtime permission-map setters replace abilities while retaining an attributed handler's all/any and skip/reject policy.

`forUser()` is a separate row-scope helper. It adds the actor's identifier column/value to the target model; it does not infer foreign keys or tenant relationships:

```php
// Appropriate when filtering the user's own User record by its id.
$userFilter->forUser($request->user());
$userFilter->apply(User::query());

// Ownership on posts should use your application's actual foreign key.
$postFilter->registerPreFilters(fn ($query) => $query->where('tenant_id', $tenantId));
$postFilter->authorizeFor($request->user());
```

## Complexity limits and throttling

```php
#[\Filterable\Attributes\FilterLimits(maxFilters: 5, maxComplexity: 25)]
class SearchPostFilter extends PostFilter
{
    #[\Filterable\Attributes\AsFilter('ids')]
    #[\Filterable\Attributes\FilterRules(['array', 'max:20'])]
    #[\Filterable\Attributes\FilterComplexity(3)]
    protected function ids(array $value): void
    {
        $this->getBuilder()->whereIn('id', $value);
    }
}
```

The setter equivalent:

```php
$filter->enableFeature('rateLimit');
$filter->setMaxFilters(5);
$filter->setMaxComplexity(25);
$filter->setFilterComplexity(['ids' => 3, 'q' => 2]);
```

Budgets are checked after authorization and transformation, before handlers run. Each effective key costs 1 by default. An array multiplies the key's weight by its length: three IDs at weight 3 cost 9. Empty/denied inputs do not count. Exceeded budgets or throttling raise `AuthorizationException`.

The limiter key includes filter class, request IP, and the legacy row-scope user's identifier when present. Defaults allow 60 attempts, with decay `max(1, ceil(complexity / 10))` seconds. Customize the supported protected hooks:

```php
protected function resolveRateLimitMaxAttempts(): int { return 30; }
protected function resolveRateLimitDecaySeconds(int $complexityScore): int { return 60; }
```

The historical `resolveRateLimitWindowSeconds()` hook remains available, but Laravel's `tooManyAttempts()` has no independent window argument; use the decay hook to control expiration.

## Result and count caching

### Choosing a result policy

Enable the caching concern separately from selecting a result/count policy:

```php
$filter->enableFeature('caching');
$filter->setCacheHandler(Cache::store('redis'));
$filter->setCacheExpiration(5); // Minutes.
$filter->cacheTags(['posts', 'tenant:'.$tenantId]);
$filter->cacheResults();        // Explicitly cache results.
$filter->cacheCount();          // Independently cache counts.
$filter->apply(Post::query());

$posts = $filter->get();
$total = $filter->count();
```

| Result mode | Attribute | Runtime API |
| --- | --- | --- |
| Explicitly enabled | `CachePolicy(results: true)` | `cacheResults()` or `cacheResults(true)` |
| Explicitly disabled | `CachePolicy(results: false)` | `cacheResults(false)` |
| Automatic heuristic | `CachePolicy(results: null)` | `autoCacheResults()`; also the default without an explicit result policy |

Explicit false prevents result-cache reads **and** writes even for complex queries or already-warm entries. Count caching remains independent:

```php
#[\Filterable\Attributes\CachePolicy(results: false, count: true, ttlMinutes: 5)]
class CountOnlyPostFilter extends PostFilter {}

$filter = app(CountOnlyPostFilter::class);
$filter->apply(Post::query());
$posts = $filter->get(); // Fresh records each time.
$total = $filter->count(); // Count may come from cache.

$filter->cacheResults(true); // Runtime override enables result caching.
$filter->cacheResults(false); // Explicitly disable it again.
$filter->autoCacheResults(); // Restore the heuristic.
$filter->cacheCount(false);  // Disable count caching separately.
$filter->disableFeature('caching'); // Disable all terminal caching.
```

`CachePolicy` enables the concern and defaults to `results: true`, `count: false`, and five minutes. Without an explicit result policy, the existing heuristic skips a single simple comparison and may cache more expensive/select queries. Choose true/false when you need predictable behavior. Zero TTL bypasses all caching regardless of result/count settings. Cache reads do not extend freshness.

### Which execution paths use the cache?

```php
$filter->apply(Post::query()); // Always applies predicates; never executes a query.
$posts = $filter->get();       // Uses the selected package result policy.
$total = $filter->count();     // Uses the selected package count policy.

// A fresh filter for a new query/lifecycle:
$posts = app(PostFilter::class)->runQuery(Post::query());
```

`Post::query()->filter($filter)->get()` uses Eloquent execution, not the package's result cache. The same applies to builder `first()`, `pluck()`, `paginate()`, and `cursor()`. Use normal Eloquent pagination when needed; Filterable does not provide a cached paginator API.

Builders with eager loading, Eloquent/base query callbacks, or before-query callbacks bypass package caching so retrieval preserves current query behavior. Callbacks registered through global scopes are also detected. A warm entry created by a callback-free query cannot replace the current builder's `afterQuery()` processing. No executable callbacks are serialized or hashed.

### Tenant isolation and freshness

Cache keys are SHA-256 identities incorporating the concrete filter/model, resolved connection configuration, database, SQL/bindings, actor identifiers, relevant options, and result/count mode. Connection identity includes driver, endpoint/port/socket, username, configured schema/search path, table prefix, and sanitized read/write configuration. Shared logical connection names and identical database names on different servers therefore remain isolated. Passwords and connection URLs are excluded; credentials are not exposed in cache keys or diagnostics.

Application-level context still matters for policy revisions, session-level tenancy/RLS, locale-dependent accessors, custom connection resolvers, and embedding versions:

```php
$filter->setOption('cache_context', [
    'tenant' => $tenantId,
    'policy_revision' => $policyRevision,
    'locale' => app()->getLocale(),
]);
```

After repointing a tenant connection, purge/reconnect it and create a fresh filter/builder. Session changes such as a manually issued `SET ROLE`/`SET search_path` are not inferred from configuration; include them in `cache_context`. A custom connection without Laravel's resolved configuration requires an explicit database namespace in `cache_context`. User ID alone is not a policy revision or complete tenant context.

### Invalidation and unsupported tags

```php
$filter->clearCache(); // Removes this query's result and count entries.

// After a relevant model change, using the same configured tag set:
$filter->clearRelatedCaches(Post::class);
```

`clearRelatedCaches()` flushes the configured tag set; the model-class argument does not infer tags. Use tenant-specific tags for tenant-specific invalidation. Stores without tag support use ordinary untagged caching; `clearRelatedCaches()` then does nothing rather than flushing the whole store. The application owns invalidation when records, embeddings, or permissions change.

## Model filter attributes

On Laravel 11 through 13, `#[UseFilter(PostFilter::class)]` connects a model to its default filter without a model trait. Use `::class` to pass the class name. The package service provider registers the Eloquent `filter()` macro automatically.

```php
// Resolve the declared filter with the current request.
$posts = Post::filter()->get();

// Existing query constraints, eager loading, and pagination remain available.
$posts = Post::where('is_available', true)->filter()->paginate(20);

// Pass application-specific options without constructing a filter.
$posts = Post::query()->filter(options: ['cache_context' => $tenantId])->get();

// Override the default with a configured instance for this query.
$filter = app(PostFilter::class)->appendFilterable('status', 'published');
$posts = Post::filter($filter)->get();
```

The default filter is resolved through Laravel's container on every call. Keep filter bindings transient: they hold mutable state and must not be singleton or scoped bindings. Calling `filter()` only changes the query; `get()`, `count()`, or `paginate()` executes it. These are ordinary Eloquent terminal methods; package result caching still requires the filter's own terminal API.

Subclasses inherit the nearest parent's `UseFilter` declaration. Add a new `#[UseFilter(OtherFilter::class)]` to override it. The declared class must implement `Filterable\Contracts\Filter` and be constructible through the container. Calling `filter()` on a model without the attribute or an existing filter scope throws a clear exception.

Existing model filter scopes, including both Filterable traits below, take precedence over the attribute and retain their arguments and return values. Remove the trait when migrating to automatic attribute resolution. The provider preserves an already registered global `filter` macro; if another package defines one, resolve the filter explicitly and call `$filter->apply(Post::query())` or use `QueryFilter` below.

For static analysis or IDEs that cannot discover runtime builder macros, a model PHPDoc such as `@method static \Illuminate\Database\Eloquent\Builder<static> filter(?\Filterable\Contracts\Filter $filter = null, ?array $options = [])` describes the static shortcut. Builder macro recognition depends on the analyzer's Laravel integration.

## Scopes and query callbacks

The original `Filterable` model trait remains fully supported on Laravel 11–13:

```php
class Post extends \Illuminate\Database\Eloquent\Model
{
    use \Filterable\Traits\Filterable;
}

$query = Post::query()->filter(app(PostFilter::class), ['cache_context' => ['tenant' => $tenantId]]);
$posts = $query->get();
```

With the trait, continue passing the filter instance explicitly, including through controller injection:

```php
<?php

namespace App\Http\Controllers;

use App\Filters\PostFilter;
use App\Models\Post;

class TraitPostController
{
    public function index(PostFilter $filter)
    {
        return Post::query()->filter($filter)->orderBy('id')->get();
    }
}
```

Both model styles are supported:

| Model registration | Query usage | Filter resolution |
| --- | --- | --- |
| `use Filterable;` | `Post::filter($filter)` | Pass the instance explicitly, as before. |
| `#[UseFilter(PostFilter::class)]` without a trait | `Post::filter()` | Resolve the declared filter through the container. |

There is no required migration. Adding `UseFilter` to a model that still uses the trait keeps the trait's scope behavior, so the filter argument remains required. To opt into automatic resolution, replace the trait with the attribute and then omit the filter argument.

Laravel 12+ offers an alternative native attributed scope trait:

```php
class Post extends \Illuminate\Database\Eloquent\Model
{
    use \Filterable\Traits\AttributeFilterable;
}

$posts = Post::query()->filter(app(PostFilter::class))->get();
```

Choose one trait per model. `Contracts\Filterable` describes the legacy `scopeFilter()` signature; it is not required for the native attributed-scope trait. Both paths mutate the existing query and preserve its model/custom builder at runtime.

An invokable adapter works without a model trait:

```php
use Filterable\QueryFilter;

$filter = app(PostFilter::class);
$query = Post::query()->tap(new QueryFilter($filter));
$posts = $filter->get(); // Package terminal caching if enabled.
```

On framework versions providing native Eloquent `pipe()` (including Laravel 13):

```php
$query = Post::query()->pipe(new QueryFilter(app(PostFilter::class)));
$posts = $query->get();
```

`tap()` is for mutation; `pipe()` returns its callback's result. `QueryFilter` returns a builder and does not execute it. For application post-processing, normal Eloquent callbacks remain available and bypass the package cache:

```php
$query = Post::query()->afterQuery(function ($posts) {
    // This callback runs on every get(), even when an equivalent cache entry exists.
    return $posts->map(fn ($post) => $post->setAttribute('display_title', strtoupper($post->title)));
});
$filter = app(PostFilter::class);
$filter->enableFeature('caching')->cacheResults();
$filter->apply($query);
$posts = $filter->get();
```

## Prefilters and fluent query chains

Use a prefilter for mandatory application constraints that must not depend on HTTP keys:

```php
$filter->registerPreFilters(function ($query) use ($tenantId) {
    $query->where('tenant_id', $tenantId)->where('is_visible', true);
});
$filter->apply(Post::query());
```

One prefilter callback is stored; put all constraints in it. Registering another replaces the previous callback. Prefilters and `forUser()` row scoping run regardless of optional feature switches.

Queued fluent additions run after registered handlers when `filterChaining` is enabled:

```php
$filter->enableFeature('filterChaining');
$filter->where('status', 'published');
$filter->where('price', '>=', 10);
$filter->whereIn('category_id', [1, 2]);
$filter->whereNotIn('id', [99]);
$filter->whereBetween('published_at', ['2026-01-01', '2026-12-31']);
$filter->orderBy('published_at', 'desc');
$posts = $filter->runQuery(Post::query());
```

Column names/operators in these APIs belong to trusted application code. `orderBy()` accepts `asc`/`desc`. Register a validating handler to expose a controlled sort option through HTTP rather than passing arbitrary identifiers from the request.

## Query optimization

```php
$filter->enableFeature('optimization');
$filter->select(['id', 'title', 'status', 'author_id']);
$filter->with(['author', 'tags']); // Application-defined relationships.
$filter->chunkSize(500);
$filter->apply(Post::query());
$posts = $filter->get();
```

Select/eager-load settings are applied during `apply()` only when `optimization` is enabled. Include the keys Eloquent needs to match relationships. Eager loading bypasses package caching because relation constraints may contain callbacks. `chunkSize()` sets chunking options; use explicit streaming APIs or enable `memoryManagement` to control materialization.

Index hints are database-specific. On MySQL, Laravel's native builder can select an application-controlled index before filtering:

```php
$query = Post::query()->useIndex('posts_status_index');
$query = $filter->apply($query);
```

The package's legacy `useIndex()` helper is retained for compatibility, but modifies the table expression; prefer the native builder API and inspect generated SQL when using hints.

## Streaming and memory management

Apply to an ordered query first. These are alternative execution styles; each retrieval executes the query:

```php
$filter = app(PostFilter::class);
$filter->apply(Post::query()->orderBy('id'));

// LazyCollection: chunked loading; process incrementally.
foreach ($filter->lazy(500) as $post) {
    processPost($post);
}

// Callback processing without retaining a result array.
$filter->lazyEach(fn ($post) => processPost($post), 500);

// Native Generator delegating to Eloquent's cursor.
foreach ($filter->cursor() as $post) {
    processPost($post);
}

// LazyCollection / Generator with configured or explicit chunk size.
foreach ($filter->stream(250) as $post) {
    processPost($post);
}
foreach ($filter->streamGenerator(250) as $post) {
    processPost($post);
}

// Chunk callback; return false to stop.
$filter->chunk(250, function ($posts) {
    foreach ($posts as $post) {
        processPost($post);
    }
});
```

`lazy()`/`stream()` chunk sizes control loading; cursor behavior also depends on driver buffering and cannot eager-load relationships in the same way as chunked queries. Use stable ordering and Laravel's native `lazyById()`/`chunkById()` on the filtered builder when updating ordering keys during iteration.

Convenience operations process input incrementally but `map()`/`filter()` collect their output into arrays:

```php
$titles = $filter->map(fn ($post) => $post->title, 250);
$expensive = $filter->filter(fn ($post) => $post->price > 100, 250);
$sum = $filter->reduce(fn ($total, $post) => $total + $post->price, 0, 250);
```

The `memoryManagement` feature selects chunked materialization for filter `get()` when a chunk size is configured:

```php
$filter->enableFeature('memoryManagement')->setOption('chunk_size', 250);
$filter->disableFeature('caching');
$filter->apply(Post::query()->orderBy('id'));
$posts = $filter->get(); // Still returns a fully materialized Collection.
```

If caching is enabled, `get()` follows the caching execution path first. Use `lazy()`/`stream()`/`cursor()` directly for incremental processing. Streaming helpers are available after `apply()` even without the feature flag. The legacy `useLazy()` setter records a preference; it does not itself switch `get()` to a streaming return type.

`lazy`, `stream`, `streamGenerator`, and `cursor` carry native `NoDiscard` attributes. PHP 8.5 warns when their return values are ignored. Consume the result, assign an intentional discard to `$_` on any supported runtime, or use `(void)` only in PHP 8.5-specific code. The annotations are inert on PHP 8.3/8.4.

## Logging, metrics, events, and errors

Configure collaborators and switches:

```php
$filter->setLogger(app(\Psr\Log\LoggerInterface::class));
$filter->enableFeatures(['logging', 'performance']);
$filter->addMetric('endpoint', 'posts.index');
$filter->apply(Post::query());

$debug = $filter->getDebugInfo();
$sql = $filter->toSql();
$bindings = $filter->getBuilder()->getBindings();
$seconds = $filter->getExecutionTime();
$metrics = $filter->getMetrics();
```

`getDebugInfo()` includes lifecycle state, applied keys, enabled features, options, SQL/bindings, and enabled performance metrics. Treat debug SQL, bindings, and application context as sensitive operational data. Performance timing measures filter **application**, not subsequent database execution. `getMetrics()` contains execution time and your added metrics; memory usage/filter counts are also emitted in the performance log context.

Lifecycle events expose the current Filter/Builder, not immutable deep snapshots:

```php
use Filterable\Events\FilterApplied;
use Filterable\Events\FilterApplying;
use Filterable\Events\FilterFailed;
use Illuminate\Support\Facades\Event;

Event::listen(FilterApplying::class, function (FilterApplying $event) {
    // About to apply; $event->filter, $event->builder, $event->options.
});
Event::listen(FilterApplied::class, function (FilterApplied $event) {
    logger()->info('Filter applied', ['keys' => $event->filters]);
});
Event::listen(FilterFailed::class, function (FilterFailed $event) {
    report($event->exception);
});
```

Validation, authorization, and invalid-definition exceptions are rethrown. The legacy generic exception hook may swallow other exceptions while marking the filter failed; `get()` then refuses to execute. To make every failure explicit in an application filter:

```php
#[\Override]
protected function handleFilteringException(\Throwable $exception): void
{
    throw $exception;
}
```

Laravel's `Conditionable` helpers are available for optional configuration:

```php
$filter->when($request->boolean('debug'), fn ($filter) => $filter->enableFeature('logging'));
$filter->unless($request->user(), fn ($filter) => $filter->disableFeature('caching'));
```

For collection pipelines, `asCollectionFilter()` exposes the effective input bag; it does not translate SQL handlers into in-memory row predicates:

```php
$inputs = collect([1])->pipe($filter->asCollectionFilter()); // Collection of effective key/value inputs.
```

## Optional Laravel 13 vector search

Use precomputed embeddings to avoid mandatory AI SDK dependencies or paid calls. This standalone filter validates the entire search payload before predicates execute:

```php
use Filterable\Attributes\AsFilter;
use Filterable\Attributes\FilterRules;
use Filterable\Filter;
use Illuminate\Support\Facades\Validator;

class SemanticPostFilter extends Filter
{
    #[AsFilter('status'), FilterRules(['in:published'])]
    protected function status(string $value): void
    {
        $this->getBuilder()->where('status', $value);
    }

    #[AsFilter('semantic'), FilterRules(['array:vector,threshold,limit'])]
    protected function semantic(array $value): void
    {
        $this->getBuilder()
            ->whereVectorSimilarTo('embedding', $value['vector'], minSimilarity: (float) ($value['threshold'] ?? 0.4))
            ->limit((int) ($value['limit'] ?? 10));
    }

    #[\Override]
    protected function validateFilterInputs(): void
    {
        parent::validateFilterInputs();
        $semantic = $this->getFilterables()['semantic'] ?? null;
        if (is_array($semantic)) {
            Validator::make($semantic, [
                'vector' => ['required', 'array', 'size:3'],
                'vector.*' => ['required', 'numeric', 'between:-1.0e20,1.0e20'],
                'threshold' => ['sometimes', 'numeric', 'between:0,1'],
                'limit' => ['sometimes', 'integer', 'between:1,100'],
            ])->after(function ($validator) use ($semantic) {
                $vector = $semantic['vector'] ?? [];
                if (is_array($vector) && count(array_filter($vector, fn ($v) => is_numeric($v) && (float) $v !== 0.0)) === 0) {
                    $validator->errors()->add('vector', 'Use a nonzero vector for cosine similarity.');
                }
            })->validate();
        }
    }
}
```

PostgreSQL setup for the example's three-dimensional vectors:

```sql
CREATE EXTENSION IF NOT EXISTS vector;
ALTER TABLE posts ADD COLUMN embedding vector(3);
CREATE INDEX posts_embedding_cosine ON posts USING hnsw (embedding vector_cosine_ops);
```

```php
$filter = new SemanticPostFilter(new \Illuminate\Http\Request([
    'status' => 'published',
    'semantic' => ['vector' => [1, 0, 0], 'threshold' => 0.8, 'limit' => 10],
]));
$filter->registerPreFilters(fn ($query) => $query->where('tenant_id', $tenantId));
$posts = $filter->runQuery(Post::query());
```

Adapt the fixed dimension and embedding column to your application. Inputs cannot choose database identifiers/operators. Similarity defaults to relevance ordering; pass `order: false` to `whereVectorSimilarTo()` before applying an explicit application-controlled `orderBy()`. Include embedding model/version and tenant/policy context in `cache_context` when caching, and invalidate when embeddings change.

Text-to-embedding conversion may be supplied through an application service/transformer; validate its final vector before query mutation. The package does not call an AI provider. Laravel 11/12 and unsupported drivers should omit this recipe. PostgreSQL/pgvector is exercised in CI; Laravel also documents MariaDB 11.7+ support. See [Laravel vector clauses](https://laravel.com/docs/13.x/queries#vector-similarity-clauses) and the [integration fixture](tests/Fixtures/VectorSearchFilter.php).

## Generators and application stubs

```bash
php artisan make:filter PostFilter                         # Legacy property declaration.
php artisan make:filter PostFilter --basic                 # Minimal legacy name handler.
php artisan make:filter PostFilter --model=Post             # Legacy search/sort example.
php artisan make:filter PostFilter --attributes            # Portable method attributes.
php artisan make:filter PostFilter --attributes --basic
php artisan make:filter PostFilter --attributes --model=Post
php artisan make:filter PostFilter --php85 --model=Post     # Explicit PHP 8.5 callable attributes.
php artisan make:filter PostFilter --attributes --force    # Overwrite an existing class.
```

Generated classes default to `App\Filters`. Model examples start with a `name` search column; change it to your schema. `--php85` selects callable variants and fails before writing on older runtimes.

Place overrides in your application's `stubs/` directory. Names are:

| Generator | Application stub names |
| --- | --- |
| Legacy | `filter.stub`, `filter.basic.stub`, `filter.model.stub` |
| Portable attributes | `filter.attributes.default.stub`, `filter.attributes.basic.stub`, `filter.attributes.model.stub` |
| PHP 8.5 attributes | `filter.attributes.default.php85.stub`, `filter.attributes.basic.php85.stub`, `filter.attributes.model.php85.stub` |

Application stubs take precedence over packaged stubs. Attribute generation supports model/basic variants, custom overrides, and `--force`; CI loads generated PHP and executes real Eloquent queries.

## API reference

| Task | Public APIs |
| --- | --- |
| Apply/execute/reset | `apply($builder, $options)`, `runQuery($builder, $options)`, `get()`, `count()`, `reset()` |
| Inputs/registration | `appendFilterable($key, $value)`, `getFilters()`, `getFilterables()`, `getCurrentFilters()`, `asCollectionFilter()` |
| Features/options | `enableFeature()`, `enableFeatures()`, `disableFeature()`, `hasFeature()`, `setOption()`, `setOptions()`, `getOptions()`, `when()`, `unless()` |
| Validation/conversion | `setValidationRules()`, `addValidationRule()`, `setValidationMessages()`, `registerTransformer()` |
| Authorization/scoping | `authorizeFor()`, `setFilterPermissions()`, `forUser()`, `registerPreFilters()` |
| Limits | `setMaxFilters()`, `setMaxComplexity()`, `setFilterComplexity()` |
| Caching | `cacheResults()`, `autoCacheResults()`, `cacheCount()`, `cacheTags()`, `setCacheExpiration()`, `getCacheExpiration()`, `setCacheHandler()`, `getCacheHandler()`, `clearCache()`, `clearRelatedCaches()` |
| Query additions | `where()`, `whereIn()`, `whereNotIn()`, `whereBetween()`, `orderBy()` |
| Optimization | `select()`, `with()`, `chunkSize()`; legacy `useIndex()` (see index-hint guidance above) |
| Incremental execution | `lazy()`, `lazyEach()`, `cursor()`, `stream()`, `streamGenerator()`, `chunk()` |
| Collected transformations | `map()`, `filter()`, `reduce()`; legacy preference setter `useLazy()` |
| Debug/metrics | `getDebugInfo()`, `toSql()`, `getMetrics()`, `getExecutionTime()`, `addMetric()`, `setLogger()`, `getLogger()` |
| Builder access | `getBuilder()` (nullable before apply), `setBuilder()`; supplying a builder alone does not apply input filters |

## Upgrading from 2.x

Version 3.0 keeps the PHP 8.3+ and Laravel 11–13 runtime floor, with intentional behavior/extension changes:

- Register every programmatic key with `AsFilter`, `$filters`, or `$filterMethodMap`. Helpers are not HTTP-invocable by discovery.
- Request inputs are captured once. Zero/false survive; transformed values and denied-key removal persist. Reset before reuse.
- Guests no longer bypass declared permissions. The default legacy hook checks Gate. Separate `authorizeFor()` from row ownership constraints.
- Invalid definitions and authorization/complexity rejections throw; they cannot return a silently partially filtered query through the generic exception hook.
- `apply()` never executes/caches result queries. Use filter `get()`/`runQuery()`/`count()` for package caching.
- The protected `applyFilterablesWithCache()` hook now returns void and only mutates predicates. Customize terminal caching through `executeQueryWithCaching()`.
- Cursor returns a real Generator. Native method `Override` is used on verified framework hooks; targeted NoDiscard diagnostics activate only on PHP 8.5.

The caching follow-up distinguishes explicit result-cache disable from automatic mode, isolates resolved database endpoints, and preserves per-query callback execution. Existing cache entries naturally miss after the identity change. Dedicated [migration notes](docs/attributes-and-migration.md) and [vector notes](docs/vector-search.md) are supplementary; the examples needed to use the package are included above.

## Development and verification

```bash
composer install
composer lint                  # Strict Duster + source syntax checks.
composer analyse               # Maximum-level Larastan, source and tests; no baseline.
composer test
composer fix                   # Duster formatting with a timestamped log.

./bin/test.sh --filter=CachePolicyRegressionTest
./bin/test.sh --test=tests/Integration/TenantCacheIsolationTest.php
./bin/test.sh --coverage --filter=CachePolicyRegressionTest
php bin/benchmark-definitions.php
```

CI exercises PHP 8.3/8.4/8.5 × Laravel 11/12/13, lowest dependencies, MySQL/Redis, PostgreSQL/pgvector/Redis, and two MySQL servers sharing a database name. Ordinary tests use SQLite; service jobs explicitly select real connections. Callback/cache policy tests and generated examples run through real requests, SQL/bindings, and returned records. Optional AI calls are never required.

Source is under `src/Filterable/`, configuration under `config/`, generator stubs under `src/Filterable/Console/stubs/`, and Testbench tests under `tests/`. Follow [AGENTS.md](AGENTS.md) for contribution conventions. Include capability changes, compatibility decisions, and verification commands in a PR; keep scripts in `bin/` executable.

## License and authors

MIT — see [LICENSE](LICENSE). Created by [Jerome Thayananthajothy](https://github.com/Thavarshan). See the [contributors](https://github.com/Thavarshan/filterable/contributors).

Inspired by [spatie/laravel-query-builder](https://github.com/spatie/laravel-query-builder) and Tighten's [Duster](https://github.com/tighten/duster).
