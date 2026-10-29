# Release Notes

## [Unreleased](https://github.com/Thavarshan/filterable/compare/v3.1.0...HEAD)

## [v3.0.0](https://github.com/Thavarshan/filterable/compare/2.1.2...3.0.0) - 2026-10-29

### Added

- Optional `AsFilter`, `FilterFeatures`, `CachePolicy`, `FilterLimits`, `FilterRules`, `TransformWith`, `RequiresPermission` and `FilterComplexity` attributes, with immutable cached method definitions and request-specific rule/transformer services.
- Laravel Gate authorization with independent actors and explicit guest/all/any/skip/reject behavior.
- Native attributed Eloquent scope adapter, invokable query callback, portable attribute generators, and opt-in PHP 8.5 callable examples.
- Optional Laravel 13 vector-search recipe and PostgreSQL pgvector integration tests without AI service dependencies.
- PR compatibility coverage for PHP 8.3/8.4/8.5 and Laravel 11/12/13, lowest dependencies, actual MySQL/PostgreSQL/Redis jobs, maximum-level static analysis, and source coverage configuration.

### Fixed

- Filter application always applies predicates to the returned builder and performs no queries, even with caching enabled or cached results present.
- A stable input snapshot preserves transformed values, denied inputs stay removed, and zero/false values remain valid. Programmatic inputs override request values; reset explicitly clears actor, row-scope and query/input state.
- Terminal cache identity includes filter/model/connection/query/bindings and user/context information. Result and count invalidation use the same tagged store; unsupported tags never flush the whole store.
- Cursor returns a real Generator by delegating to Eloquent's LazyCollection. Targeted PHP 8.5 NoDiscard diagnostics and verified PHP 8.3 Override hooks strengthen extension contracts.
- Resolved all 1,100+ findings exposed by the original broken static-analysis configuration. Maximum-level analysis now passes across source and tests without a baseline or ignored-error list.

### Breaking changes and migration

- Programmatic filter inputs must be registered with `AsFilter`, `$filters` or `$filterMethodMap`; arbitrary helpers are no longer request-invocable.
- Declared permissions no longer authorize guests automatically. The default legacy permission hook now consults Laravel Gate. Invalid definitions and authorization/complexity rejections fail explicitly.
- `apply()` never populates a result cache. Use `$filter->get()`, `runQuery()` or `$filter->count()` for package caching; Eloquent builder `get()` executes normally.
- The protected `applyFilterablesWithCache()` hook now returns void and only mutates the query. Customize terminal caching through `executeQueryWithCaching()`.
- PHP 8.3+ and Laravel 11–13 support remain unchanged. Inline callable attributes require PHP 8.5 and remain isolated from older parsers.

See the [migration and precedence guide](https://github.com/Thavarshan/filterable/blob/3.0.0/docs/attributes-and-migration.md) and [vector recipe](https://github.com/Thavarshan/filterable/blob/3.0.0/docs/vector-search.md).

Implemented through [PR #50](https://github.com/Thavarshan/filterable/pull/50), covering #37 and #40–#49.

## [v2.1.2](https://github.com/Thavarshan/filterable/compare/v2.1.1...v2.1.2) - 2026-03-19

### Added

- Added support for Laravel 13.x components.

### Changed

- Updated CI test matrix to include Laravel 13.x with Orchestra Testbench 11.x.
- Expanded PHP support to include PHP 8.5 and added PHP 8.5 coverage in the CI test matrix.
- Raised `spatie/laravel-package-tools` minimum to `^1.93` to guarantee Illuminate 13 compatibility.
- Broadened dev `orchestra/testbench` support to `9.*|10.*|11.*` to match the CI matrix for Laravel 11/12/13.

## [v2.1.1](https://github.com/Thavarshan/filterable/compare/v2.1.0...v2.1.1) - 2025-12-05

### Added

* No new features in this release; internal improvements and fixes only.

### Changed

* Updated `FilterDependencyInjectionTest.php` to improve coverage, including a query assertion and more consistent request setup.
* Refined `FilterableServiceProvider.php` to use a consistent pattern when wiring filters through the container.
* Cleaned up code style by updating docblocks and removing a redundant constructor for better maintainability.

### Fixed

* Ensured `Filter` instances resolved from the dependency injection container always receive the current HTTP request.

## [v2.1.0](https://github.com/Thavarshan/filterable/compare/v2.0.1...v2.1.0) - 2025-10-14

### Added

- Publishable configuration defaults (`config/filterable.php`) now seed feature toggles, runtime options, and cache TTLs automatically during filter construction.
- New lifecycle events (`FilterApplying`, `FilterApplied`, `FilterFailed`) fire around `apply()` so listeners can plug into telemetry, metrics, or alerts without overriding the base class.
- Streaming helpers (`stream()`, `streamGenerator()`) expose LazyCollection and generator pipelines for truly memory-safe traversal.
- Rate limiting hooks allow filters to override default attempt counts, windows, and decay values per request.
- Added an end-to-end integration test covering filtering, caching, streaming, and lifecycle signals against the in-memory Testbench harness.

### Changed

- Rate limiter keys incorporate the authenticated user identifier (when provided via `forUser()`), reducing collisions across shared IPs.
- Memory management now streams results under the hood before materialising them during `get()` when enabled.
- Documentation refreshed to surface lifecycle events, streaming helpers, rate limiting overrides, and configuration presets.

### Fixed

- Prevents rate limiter collisions with richer key composition and hookable decay windows.
- Ensures streaming helpers respect the filter lifecycle by requiring `apply()` before invocation.

## [v2.0.1](https://github.com/Thavarshan/filterable/compare/v2.0.0...v2.0.1) - 2025-05-14

### Added

- Focuses on the new tests added to verify the fix and prevent regression
- Emphasizes that this improves the test coverage for edge cases

### Changed

- Highlights the improvements to error handling and resilience
- Frames the changes as positive enhancements to the existing code
- Uses professional, descriptive language without being too technical

### Fixed

- Clearly identifies the specific issue that was fixed
- References the GitHub issue number for traceability
- Includes the exact error message for users who might be searching for a solution

**Full Changelog**: [https://github.com/Thavarshan/filterable/compare/2.0.0...2.0.1](https://github.com/Thavarshan/filterable/compare/2.0.0...2.0.1)

## [v2.0.0](https://github.com/Thavarshan/filterable/compare/v1.2.0...v2.0.0) - 2025-05-13

### Added

- Added support for Laravel 12.x
- Added feature flag system for more granular control over filter capabilities
- Added comprehensive test suite with 100+ tests for all traits
- Added `HandlesUserScope` trait for easily filtering by authenticated user
- Added `HandlesRateLimiting` trait to prevent abuse via complex queries
- Added `ValidatesFilterInput` trait for input validation
- Added `MonitorsPerformance` trait for tracking and analyzing filter performance
- Added `TransformsFilterValues` trait for transforming input values
- Added `SupportsFilterChaining` trait for fluent API method chaining
- Added `InteractsWithLogging` trait for improved debugging and auditing
- Added `OptimizesQueries` trait for better query performance
- Added `ManagesMemory` trait for handling large datasets efficiently
- Added support for custom pre-filters via the `HandlesPreFilters` trait
- Added new artisan command (`make:filter`) with support for model-specific filters

### Changed

- Major architecture overhaul with modular trait-based design
- Improved SmartCaching with better cache key generation
- Updated all Cache interactions to use Carbon instances for TTL values
- Enhanced Filter base class with more robust constructor injection
- Standardized return types across all methods for better type safety
- Updated documentation with comprehensive examples for all features
- Improved error handling with more descriptive exceptions
- Modernized test cases to use latest PHPUnit assertions
- Switched to feature-based activation rather than global static methods

### Fixed

- Fixed cache key generation for array values
- Fixed memory leaks when dealing with large datasets
- Fixed issues with query builder method chaining
- Fixed inconsistent behavior with filter application
- Fixed validation errors not being properly propagated
- Fixed user scoping not being applied correctly in some scenarios
- Fixed rate limiting bypass techniques
- Fixed performance monitoring accuracy
- Fixed logging inconsistencies when features are toggled

## [v1.2.0](https://github.com/Thavarshan/filterable/compare/v1.1.7...v1.2.0) - 2025-02-25

### Added

- Support for Laravel `^12`

## [v1.1.7](https://github.com/Thavarshan/filterable/compare/v1.1.6...v1.1.7) - 2025-02-23

### Added

- Introduced the `FilterableServiceProvider` to register the `MakeFilterCommand`.
- Added a new `Filterable` interface to define the contract for the Filterable trait.
- Added a new `Filter` interface to define the contract for the Filter class.

### Changed

- Added type hints for method parameters and return types to improve code clarity and type safety.
- Improved the `Filterable` trait to ensure compatibility with PHP 8.4.
- Enhanced the `Filter` class with better type hinting and method documentation.
- Updated the `FilterableTest` to include the necessary setup for bootstrapping the Laravel application.

### Fixed

- Fixed an issue where the `config` class was not available during tests by bootstrapping the Laravel application in the test setup.
- Corrected the test case to ensure the `apply` method is called correctly in the `filter_throws_exception_when_filter_application_fails` test.

## [v1.1.6](https://github.com/Thavarshan/filterable/compare/v1.1.5...v1.1.6) - 2024-09-25

### Changed

- Extend compatibility to PHP 8.3

### Fixed

- Laravel 9 compatibility issues

## [v1.1.5](https://github.com/Thavarshan/filterable/compare/v1.1.4...v1.1.5) - 2024-09-24

- Minor dependency updates for security

## [v1.1.4](https://github.com/Thavarshan/filterable/compare/v1.1.3...v1.1.4) - 2024-08-25

### Changed

- Minor dependency updates for security

## [v1.1.3](https://github.com/Thavarshan/filterable/compare/v1.1.2...v1.1.3) - 2024-07-14

### Changed

- Updated dependencies

## [v1.1.2](https://github.com/Thavarshan/filterable/compare/v1.1.1...v1.1.2) - 2024-05-16

### Changed

- Modified the buildCacheKey method to sort and normalise `filterables` before generating the cache key. This change reduces the number of unique keys and helps mitigate cache pollution issues. (See PR [#18](https://github.com/Thavarshan/filterable/pull/18))
  Caching has now been changed to be disabled by default. This change provides more control over when caching is used, helping to prevent unnecessary cache pollution.

### Fixed

- Fixed cache pollution issues caused by the generation of too many unique keys. This was achieved by limiting the number of unique filter combinations that can be cached. (See issue [#17](https://github.com/Thavarshan/filterable/issues/17) and PR [#18](https://github.com/Thavarshan/filterable/pull/18))

## [v1.1.1](https://github.com/Thavarshan/filterable/compare/v1.1.0...v1.1.1) - 2024-05-01

### Added

- **Compatibility support for newer PHP versions:** Updated `brick/math` requirement from PHP `^8.0` to `^8.1` to embrace the latest PHP features and improvements.

### Changed

- **Updated `brick/math` from `0.11.0` to `0.12.1`:** Includes performance optimizations and bug fixes to enhance mathematical operations.
- **Updated `laravel/framework` from `v10.48.5` to `v10.48.10`:** Rolled in new minor features and improvements to the Laravel framework that benefit the stability and security of applications using `filterable`.
- **Updated `symfony/console` from `v6.4.6` to `v6.4.7`:** Enhanced compatibility with other Symfony components, improving integration and usage within Symfony-based projects.
- **Updated development dependencies:**
  - `phpunit/phpunit` from `^9.0` to `^10.1` for advanced unit testing capabilities.
  - `vimeo/psalm` from `5.0.0` to `5.16.0` for improved static analysis and code quality checks.
  

### Fixed

- **Security patches and minor bugs:** All updated dependencies include patches for known vulnerabilities and fixes for various minor bugs, enhancing the security and reliability of the `filterable` package.

## [v1.1.0](https://github.com/Thavarshan/filterable/compare/1.0.6...v1.1.0) - 2024-04-23

### Added

- **Logging Support in Filter Class**: Introduced comprehensive logging capabilities to enhance debugging and operational monitoring within the `Filter` class. This update allows developers to trace the application of filters more effectively and can be critical for both development and production debugging scenarios. [#12](https://github.com/Thavarshan/filterable/pull/12)
  - **Dynamic Logging Controls**: Added methods `enableLogging()` and `disableLogging()` to toggle logging functionality at runtime, allowing better control over performance and log verbosity depending on the environment.
  - **Integration with `Psr\Log\LoggerInterface`**: Ensured flexibility in logging implementations by integrating with the standard PSR-3 logger interface. Developers can now inject any compatible logging library that adheres to this standard, facilitating customized logging strategies.
  - **Conditional Log Statements**: Added conditional logging throughout the filter application process to provide granular insights into key actions and decisions. This feature is designed to help in pinpointing issues and understanding filter behavior under various conditions.
  - **Unit Tests for Logging**: Extended the test suite to include tests verifying that logging behaves as expected under different configurations, ensuring that the new functionality is robust and reliable.
  

### Changed

- Deprecated instance method `setUseCache()` in favor of static method `enableCaching()` for improved consistency and clarity. This change aligns with the existing static property `useCache` and enhances the discoverability of caching-related functionality. [#12](https://github.com/Thavarshan/filterable/pull/12)

### Fixed

- Minor bug fixes and performance optimizations to enhance stability and efficiency.

## [1.0.6](https://github.com/Thavarshan/filterable/compare/v1.0.5...1.0.6) - 2024-04-14

### Changed

- Refactor `useCache` instance property to static
- Refactor `setUseCache` instance method to `enableCaching` static method for use in service provider classes

## [v1.0.5](https://github.com/Thavarshan/filterable/compare/v1.0.4...v1.0.5) - 2024-04-10

### Changed

- Implement filter scope for Eloquent Models to use with `Filterable` trait

## [v1.0.4](https://github.com/Thavarshan/filterable/compare/v1.0.3...v1.0.4) - 2024-04-10

### Fixed

- Fix "Fatal Error: Type of `App\Filters\EventFilter::$filters` Must Be Array" (#8)

## [v1.0.3](https://github.com/Thavarshan/filterable/compare/v1.0.2...v1.0.3) - 2024-04-10

### Fixed

- Fix for Argument Acceptance in `make:filter` Command [#6](https://github.com/Thavarshan/filterable/issues/6)

## [v1.0.2](https://github.com/Thavarshan/filterable/compare/v1.0.1...v1.0.2) - 2024-04-10

### Fixed

- Fix Service Provider Namespace in `composer.json` [#5](https://github.com/Thavarshan/filterable/issues/5)

## [v1.0.1](https://github.com/Thavarshan/filterable/compare/v1.0.1...v1.0.0) - 2024-04-10

### Fixed

- Fix `nesbot/carbon` dependency version issue [#3](https://github.com/Thavarshan/filterable/issues/3)

## v1.0.0 - 2024-04-10

Initial release.

## [v3.1.0](https://github.com/Thavarshan/filterable/compare/3.0.0...v3.1.0) - 2026-10-29

### Fixed

- Isolate cached results and counts when a shared tenant connection switches between servers with identically named databases. Cache identity now fingerprints resolved driver, endpoint/port/socket, database, username, configured schema/search path and sanitized read/write configuration. Passwords and connection URLs are excluded; unidentified custom connections require an explicit `cache_context` namespace.
- Honor `CachePolicy(results: false)` and `cacheResults(false)` without falling back to automatic result caching. Explicit disable bypasses result-cache reads and writes, including warm entries, while count caching remains independent.
- Preserve current Eloquent/base query callback output and execution frequency by bypassing package caching for callback-bearing builders. Scoped query callbacks and eager loading are also handled without invoking or serializing executable closures during inspection.

### Added

- `autoCacheResults()` and `CachePolicy(results: null)` explicitly restore automatic result caching. Existing boolean setter signatures and protected property types remain compatible.
- A self-contained README covering registration, attributes and legacy setters, lifecycle/reset, precedence, validation/transformation factories, Gate/row scope, limits, caching, scopes/query callbacks, optimization, streaming, logging/events/errors, vector search, generators and migration.
- Executable README examples and regression coverage for tenant isolation, count-only caching, warm-cache callbacks and custom connections. Service CI verifies two MySQL servers sharing a database name with a shared Redis store.

### Compatibility and validation

- PHP 8.3–8.5 and Laravel 11–13 support is retained. This minor release adds explicit automatic-policy APIs alongside the caching corrections.
- Local checks: strict lint, maximum-level static analysis with zero findings/no baseline, 222 tests and 700 assertions, plus focused source coverage.
- Final PR checks passed all nine PHP/Laravel combinations, lowest dependencies, MySQL/Redis, PostgreSQL/pgvector/Redis and CodeQL. The README's actual quick-start classes/controller request, legacy filter and vector rejection example run in the suite.
- Database cache identity changes naturally invalidate earlier entries. Continue to provide tenant/policy/session context in `cache_context` when configuration alone cannot identify application authorization state.

Implemented in [PR #54](https://github.com/Thavarshan/filterable/pull/54), resolving #51, #52 and #53.
