# CLAUDE.md

Guidance for Claude Code on how to contribute safely and effectively to this repository.

## Project Overview

Filterable is a Laravel package that converts HTTP request parameters into composable Eloquent query filters. The abstract `Filter` class in `src/Filterable/Filter.php` orchestrates the pipeline and delegates optional capabilities—validation, caching, logging, rate limiting, query optimisation, memory management, etc.—to traits in `src/Filterable/Concerns/`. Eloquent models opt in via `Filterable\Traits\Filterable`, and the `make:filter` Artisan command (with stubs in `src/Filterable/Console/stubs/`) scaffolds new filters.

## Key Paths

- `src/Filterable/Filter.php` – lifecycle (`apply`, `get`, `runQuery`, `reset`) and feature toggles.
- `src/Filterable/Concerns/` – concern traits; enable them through `$this->enableFeature()`.
- `src/Filterable/Contracts/` & `src/Filterable/Traits/` – interfaces and Eloquent integration.
- `bin/` – executable scripts used by Composer (`lint.sh`, `fix.sh`, `test.sh`).
- `tests/` – Orchestra Testbench suite with fixtures under `tests/Fixtures/`.

## Development Workflow

```bash
composer install                # install dependencies
composer lint                   # run Duster + syntax checks via bin/lint.sh
composer fix                    # apply Pint/Duster formatting (logs to file)
composer test                   # run PHPUnit through bin/test.sh

./bin/test.sh --filter=HandlesRateLimitingTest   # focused tests
./bin/test.sh --coverage                         # coverage (requires Xdebug)
./bin/test.sh --parallel                         # parallel runs
./bin/lint.sh --strict                           # non-zero exit on lint issues
vendor/bin/phpstan analyse                       # static analysis (level max)
```

`phpunit.xml.dist` defaults to a MySQL connection; provide compatible env vars or stub the driver locally when running the suite.

## Architectural Notes

- Filters transition through states (`initialized` → `applying` → `applied|failed`) and cannot be reused without `reset()`.
- Constructor dependencies automatically enable features (`Cache` ⇒ `caching`, `LoggerInterface` ⇒ `logging`).
- Request keys are mapped to camelCase methods via `HandlesFilterables`; override `$filterMethodMap` for custom naming.
- `InteractsWithCache` builds deterministic cache keys by sorting/sanitising filterables and including user scope; `SmartCaching` augments this with tag support and heuristic caching.
- `ManagesMemory` exposes `lazy()`, `chunk()`, `cursor()`, `map()`, `filter()`, `reduce()` for large result sets.
- `SupportsFilterChaining` queues fluent query constraints that execute after request-driven filters.

## Coding Conventions

- Follow PSR-12 (four-space indent, ordered imports, trailing commas in multi-line arrays).
- Keep namespaces aligned with directory structure (`Filterable\Concerns\InteractsWithCache`).
- Expose new behaviour through feature flags instead of ad-hoc booleans; extend `$features` map when introducing traits.
- Reuse logging helpers (`logInfo`, `logWarning`, etc.) rather than invoking the logger directly.
- Document non-obvious logic (e.g. rate-limit calculations, cache-key overrides) with concise docblocks.

## Testing Expectations

- Mirror existing concern tests (e.g. `CachingTest.php`, `HandlesRateLimitingTest.php`) when adding features; use Mockery for collaborators.
- Assert filter state via `getDebugInfo()` and ensure both success and failure paths are covered.
- Strategies that touch caching, rate limiting, or performance should include tests for null users, permission-denied filters, and repeated runs.
- Run `composer test` and, where relevant, `./bin/test.sh --coverage` before opening PRs.

## Common Patterns

### Creating a Filter

```php
class PostFilter extends Filter
{
    protected array $filters = ['status', 'published_at'];

    public function __construct(Request $request)
    {
        parent::__construct($request);

        $this->enableFeatures(['validation', 'caching', 'performance']);
        $this->setValidationRules([
            'status' => 'in:draft,published,archived',
            'published_at' => 'date',
        ]);
    }

    protected function status(string $value): Builder
    {
        return $this->getBuilder()->where('status', $value);
    }
}
```

### Adding a Concern

1. Create the trait in `src/Filterable/Concerns/YourTrait.php`.
2. Add a feature flag (e.g. `'yourFeature' => false`) to `$features` in `Filter.php`.
3. Include the trait in `Filter` and gate logic behind `hasFeature('yourFeature')`.
4. Cover the behaviour with dedicated PHPUnit tests and update documentation (README/AGENTS).

## Watch-outs

- Never call `apply()` twice without `reset()`—the filter will throw.
- Avoid using global helpers (`request()`, `auth()`) inside traits; rely on injected `Request` or explicit parameters.
- Keep generator stubs in sync with new features when altering the base filter API.
- Do not bypass concern helpers (e.g. building cache keys manually) unless overriding with documented alternatives.
