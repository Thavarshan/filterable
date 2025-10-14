# Copilot Coding Guidelines

## Domain Context
- This package centres on the abstract `Filter` class in `src/Filterable/Filter.php`, extended by application-specific filters that receive an `Illuminate\Http\Request`.
- Optional behaviour (validation, caching, logging, rate limiting, memory optimisation, etc.) is implemented via traits in `src/Filterable/Concerns/`; Copilot-generated code must compose with these traits instead of duplicating logic.
- Eloquent models opt into filtering by using `Filterable\Traits\Filterable`; Artesian generator stubs live in `src/Filterable/Console/stubs/`.

## Coding Rules
- Follow PSR-12: four-space indentation, trailing commas in multi-line arrays, strict type hints, and ordered imports.
- Prefer fluent APIs and immutable-looking helpers; expose feature toggles via `$this->enableFeature()` rather than bespoke flags.
- When adding new filter methods, camelCase the method name to match the request key (`status` → `status()`), or map via `$filterMethodMap`.
- Respect existing caches and logging patterns by reusing helpers (`buildCacheKey()`, `logInfo()`); do not access the logger or cache container directly.
- Keep public APIs typed and documented; add succinct docblocks if behaviour is non-obvious (e.g. transforms, rate limits).

## Testing Expectations
- Every new concern or feature flag should be covered by PHPUnit tests under `tests/`, using Orchestra Testbench.
- Mock collaborators (cache, logger, rate limiter) with Mockery, and assert on state using `getDebugInfo()` or dedicated getters.
- Provide happy-path, edge, and failure tests—mirror patterns from existing concern tests like `CachingTest.php` or `HandlesRateLimitingTest.php`.

## Anti-Patterns to Avoid
- Do not re-run `apply()` on the same filter without calling `reset()`.
- Avoid duplicating concern logic directly inside filters; extend or compose existing traits instead.
- Do not introduce framework-specific globals (`request()`, `auth()`) inside reusable traits—inject dependencies through the constructor or method parameters.
- Refrain from adding commands or scripts outside the `bin/` directory or Composer scripts without project-owner approval.
