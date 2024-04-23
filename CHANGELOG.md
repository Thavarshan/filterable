# Release Notes

## [Unreleased](https://github.com/Thavarshan/filterable/compare/v1.1.0...HEAD)

## [v1.1.0](https://github.com/Thavarshan/filterable/compare/1.0.6...v1.1.0) - 2024-04-23

### Added

- **Logging Support in Filter Class**: Introduced comprehensive logging capabilities to enhance debugging and operational monitoring within the `Filter` class. This update allows developers to trace the application of filters more effectively and can be critical for both development and production debugging scenarios. [#12](https://github.com/Thavarshan/filterable/pull/12)
  - **Dynamic Logging Controls**: Added methods `enableLogging()` and `disableLogging()` to toggle logging functionality at runtime, allowing better control over performance and log verbosity depending on the environment.
  - **Integration with `Psr\Log\LoggerInterface`**: Ensured flexibility in logging implementations by integrating with the standard PSR-3 logger interface. Developers can now inject any compatible logging library that adheres to this standard, facilitating customized logging strategies.
  - **Conditional Log Statements**: Added conditional logging throughout the filter application process to provide granular insights into key actions and decisions. This feature is designed to help in pinpointing issues and understanding filter behavior under various conditions.
  - **Unit Tests for Logging**: Extended the test suite to include tests verifying that logging behaves as expected under different configurations, ensuring that the new functionality is robust and reliable.
  

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
