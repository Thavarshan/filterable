# Attribute API and migration to 3.0

Version 3.0 retains PHP 8.3–8.5 and Laravel 11–13. It is a major release because registration and permission enforcement are stricter: programmatic inputs must name registered handlers, guests no longer bypass declared permissions, invalid definitions fail before query mutation, and authorization/complexity rejection always throws. Existing property and setter APIs remain available. The protected `applyFilterablesWithCache()` hook now mutates the query and returns void; move result caching customizations to `executeQueryWithCaching()`.

## Registration

Legacy declarations remain supported:

```php
protected array $filters = ['status'];
protected array $filterMethodMap = ['published_after' => 'publishedAfter'];
```

The equivalent attribute declares the external key directly:

```php
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

    #[AsFilter('published_after'), FilterRules(['date']), TransformWith(DateTransformer::class)]
    protected function publishedAfter(\Carbon\CarbonImmutable $value): void
    {
        $this->getBuilder()->whereDate('published_at', '>=', $value);
    }
}

class DateTransformer
{
    public function __invoke(string $value): \Carbon\CarbonImmutable
    {
        return \Carbon\CarbonImmutable::parse($value);
    }
}
```

`AsFilter` handlers must be public/protected, non-static, and accept one value. Additional optional parameters are allowed; by-reference and variadic first parameters are rejected. Helpers are never discovered from request keys. Duplicate aliases and conflicting legacy maps fail clearly. Matching legacy/attribute mappings are allowed. Inherited and trait methods retain their attributes; overriding a method replaces its metadata, so repeat `AsFilter` on an override to keep registration. Method policies are read only for `AsFilter` methods; existing legacy concern maps remain available.

`DefinitionRegistry::forClass()` shares readonly key/method definitions only. `DefinitionRegistry::clear()` supports test isolation and application reloads. Rule objects, factories, transformers, requests, users, builders, results and closures are never cached in that registry. Run `php bin/benchmark-definitions.php` to compare cold and repeated resolution.

## Defaults and precedence

| Configuration | Resolution |
| --- | --- |
| Existing protected properties | Remain the initial defaults, with the historical global-config override behavior |
| Global `filterable.defaults` | Applied during construction, before attributes |
| Inherited class attributes | Applied from oldest ancestor to nearest parent |
| Concrete class attributes | Override inherited attributes |
| Same-class concern and feature attributes | `FilterFeatures` explicitly overrides concern auto-enablement |
| Method attributes | Supply rules, transformations, Gate requirements and complexity weights; enable their concerns unless class feature defaults override them |
| Explicit runtime setters / constructor code after `parent::__construct()` | Override resolved defaults, including explicit false and zero |
| `apply($query, $options)` | Options replace matching instance options |

```php
#[\Filterable\Attributes\CachePolicy(ttlMinutes: 5, results: true, count: true, tags: ['posts'])]
#[\Filterable\Attributes\FilterLimits(maxFilters: 5, maxComplexity: 25)]
#[\Filterable\Attributes\FilterFeatures(['logging' => false])]
class CachedPostFilter extends PostFilter {}

$filter->disableFeature('caching'); // always wins over CachePolicy
```

Unknown feature names and negative TTL/limits/weights throw. Zero TTL disables terminal caching. Cache injection supplies a repository without enabling caching. Logger injection retains its existing logging-enablement behavior. All concerns can still be explicitly disabled. Runtime permission-map setters replace abilities while retaining an attributed method's all/any and reject policy.

## Input lifecycle

The first input read captures the supplied Request once. `appendFilterable()` values override request keys. Missing, null, empty string and empty array values are omitted; integer zero, string zero and false remain valid inputs. Call `appendFilterable()` before `apply()` and register every key with `AsFilter`, `$filters` or `$filterMethodMap`.

Application order is permission checks, raw-value validation, typed transformation, normalized complexity/rate checks, optimization, user scope, prefilters, handlers and custom query chains. Invalid raw values raise `ValidationException` before predicates execute. Getters, diagnostics and cache identity never reload denied or transformed request values. `reset()` clears input snapshots, applied inputs, query, error, chains, row-scope user and authorization actor, then rereads the same supplied Request on the next application. Configuration, rule/transformer registrations and runtime options persist. For a different request or job, resolve a new transient filter; never register a mutable filter as a singleton or scoped service.

`FilterRules` supports Laravel rule objects in its rule array and custom messages. For rules depending on application/request state, implement `Filterable\Contracts\RuleFactory` with `__invoke(Request $request): array|string` and pass `factory: MyRuleFactory::class`. Factories and invokable transformer classes resolve from the container per filter instance. `TransformWith` also accepts application-defined static method arrays and named functions. HTTP values never select executable callables.

## Authorization and complexity

```php
#[AsFilter('email')]
#[\Filterable\Attributes\RequiresPermission(['view-email', 'export-data'], any: false, reject: true)]
#[\Filterable\Attributes\FilterComplexity(5)]
protected function email(string $value): void
{
    $this->getBuilder()->where('email', $value);
}
```

`authorizeFor($actor)` selects the Gate actor independently of row scoping. Otherwise the Request user, then legacy `forUser()` user, supplies the actor. Explicit `authorizeFor(null)` means guest. Guests always fail declared requirements. All abilities are required by default; `any: true` accepts any one. Denied inputs are skipped by default, or raise `AuthorizationException` with `reject: true`. Skipped keys do not reappear in effective inputs or applied-filter diagnostics. Laravel controller `#[Authorize]` is not interpreted on Filter methods.

Legacy `setFilterPermissions()` uses the documented `userHasPermission()` extension hook; its default now checks Laravel Gate. No third-party permissions package is required. `forUser()` still adds the authenticated identifier column to the target model; it is not a tenant ownership relationship. Use application prefilters for tenant predicates.

Complexity is computed after transformation and permissions, before predicates. Array values multiply their handler's weight by array length. `FilterLimits` and `FilterComplexity` enable rate limiting; explicitly disable the concern if needed. Laravel's `tooManyAttempts()` receives key/attempts; `hit()` receives decay seconds. The historical `resolveRateLimitWindowSeconds()` hook remains available, but Laravel's limiter does not accept a separate window argument; customize `resolveRateLimitDecaySeconds()` to control expiration.

## Query execution and caching

`apply()` always mutates and returns the builder without executing it, on cache hits and misses. Eloquent `->filter($filter)->get()` performs normal Eloquent execution. To use package result caching, call `$filter->get()` or `runQuery()`; call `$filter->count()` for count caching. SQL/bindings include predicates, scopes, vector/threshold/order/limits, and cache identity also includes concrete filter/model, connection/database, row-scope/authorization actor identifiers, result/count mode and `cache_context`. Include tenant, policy revision, locale and embedding-model/version context when relevant:

```php
$filter->setOption('cache_context', ['tenant' => $tenantId, 'policy' => $policyRevision]);
$filter->apply(Post::query());
$posts = $filter->get();
```

Result caching skips eager-load callbacks because closures cannot be represented safely in a cache key. Tags are used only on supporting stores. `clearCache()` removes result/count entries from the same tagged repository. `clearRelatedCaches()` flushes only supported tags, never an entire untagged store. TTL is in minutes and does not extend on reads. Consumer code owns invalidation and relevant authorization context; user ID alone is not a policy revision.

## Framework/native integrations

| Feature | Compatibility |
| --- | --- |
| Package attributes, readonly definitions, native method Override | PHP 8.3+, Laravel 11–13 |
| Existing `Traits\Filterable` / `Contracts\Filterable` | Laravel 11–13 |
| Optional `Traits\AttributeFilterable` | Laravel 12+ `#[Scope]`; use instead of the legacy trait, not both |
| `new QueryFilter($filter)` passed to Eloquent `tap()` | Laravel 11–13; mutates without execution |
| Eloquent `pipe(new QueryFilter($filter))` | Frameworks exposing native `pipe()`; returns a builder, not cached results |
| Inline callable attribute arguments and pipe syntax | PHP 8.5 only |
| Native `NoDiscard` warnings | PHP 8.5; annotations remain inert on 8.3/8.4 |
| Vector recipe | Laravel 13 with PostgreSQL pgvector; see [recipe](vector-search.md) |

Only `lazy()`, `cursor()`, `stream()` and `streamGenerator()` carry `NoDiscard`. The cursor remains a native Generator and delegates with `yield from` to Eloquent's LazyCollection. Intentional discards can use a temporary variable on every supported PHP version or `(void)` on PHP 8.5. Provider overrides target `PackageServiceProvider::{configurePackage,packageRegistered,getPackageBaseDir}`. Generator overrides target `GeneratorCommand::{getStub,getDefaultNamespace,buildClass,getOptions}`; constructors and package-specific helpers are not annotated.

PHP 8.5 inline example (keep in a version-specific file):

```php
#[TransformWith(static function (string $value): string {
    return $value |> trim(...) |> strtoupper(...);
})]
```

Use a static anonymous function, with no captured variables. First-class callables such as `TransformWith(strtoupper(...))` are also supported. PHP 8.3/8.4 cannot parse these attribute arguments; a runtime condition inside the file does not protect them. Version-specific fixtures use `.php85` and load only under PHP 8.5. No fake global core attributes are defined.

## Generators and verification

`make:filter PostFilter --attributes` emits portable method attributes. Combine with `--basic` or `--model=Post`, application `stubs/filter.attributes.{basic,default,model}.stub` overrides, and `--force`. `--php85` explicitly selects callable variants and fails before writing on older runtimes. Their application override names end in `.php85.stub`. Legacy stub names and options remain supported.

Run `composer install`, `composer lint`, `composer analyse` and `composer test`. Static analysis runs at maximum level across source and tests without a baseline or ignored findings. CI exercises the PHP 8.3/8.4/8.5 × Laravel 11/12/13 matrix, lowest dependencies, MySQL/Redis and PostgreSQL/pgvector/Redis. Service jobs explicitly select their driver through `FILTERABLE_TEST_DB`; ordinary tests use SQLite. The optional-service tests skip only outside these jobs. See the workflow for reproducible local connection variables.

Upstream references: [Laravel scopes](https://laravel.com/docs/13.x/eloquent#local-scopes), [query callbacks](https://laravel.com/docs/13.x/queries#reusable-query-components), [Gate](https://laravel.com/docs/13.x/authorization), [PHP 8.5 features](https://www.php.net/manual/en/migration85.new-features.php), [NoDiscard](https://www.php.net/manual/en/class.nodiscard.php).
