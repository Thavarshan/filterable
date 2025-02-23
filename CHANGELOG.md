# Release Notes

## [Unreleased](https://github.com/Thavarshan/filterable/compare/v1.1.7...HEAD)

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
