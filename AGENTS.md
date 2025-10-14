# Repository Guidelines

## Project Structure & Module Organization

Source code lives under `src/Filterable/`, centred around the abstract `Filter` base class and trait-based “concerns” under `src/Filterable/Concerns/`. Contracts and Eloquent helpers are in `src/Filterable/Contracts/` and `src/Filterable/Traits/`. The Artisan generator and stubs are in `src/Filterable/Console/`. Executable tooling scripts are stored in `bin/`, shared media in `assets/`, and tests (plus fixtures) in `tests/`, which boot an Orchestra Testbench environment. Composer already maps `Filterable\\` and the testing namespaces; place any new factories inside `database/factories` to maintain autoloading.

## Build, Test, and Development Commands

Run `composer install` to hydrate dependencies, then rely on the Composer scripts: `composer lint` (delegates to `bin/lint.sh` for Duster + syntax checks), `composer fix` (formats via Pint/Duster and saves a log), and `composer test` (wraps `bin/test.sh` which accepts flags such as `--filter=HandlesRateLimitingTest`, `--coverage`, `--parallel`, or `--test=tests/HandlesFilterablesTest.php`). When adding commands, keep the scripts directory executable (`chmod +x bin/*.sh`).

## Coding Style & Naming Conventions

Code follows PSR-12 with four-space indentation enforced by Pint/Duster. Match namespaces to paths (`Filterable\\Concerns\\OptimizesQueries`, etc.) and stick to the existing naming patterns: suffix traits with the capability (`ManagesMemory`), concrete filters with `Filter`, and console commands under `Console`. Keep feature toggles (`$features`) and options arrays cohesive—extend the existing map rather than inventing new flags. Prefer expressive method-level docblocks when behaviour is subtle (e.g. cache key generation), otherwise lean on descriptive naming.

## Testing Guidelines

The suite is PHPUnit-based (`phpunit.xml.dist`) and runs inside Orchestra Testbench. Follow the established pattern of placing concern-specific tests at the project root (e.g. `CachingTest.php`, `HandlesRateLimitingTest.php`) and keep reusable doubles under `tests/Fixtures/`. Use partial mocks for collaborators (cache, logger, rate limiter) and prefer data providers or inline anonymous filters for edge cases. Generate coverage with `./bin/test.sh --coverage --filter=Namespace\\Class` before shipping complex features, and assert on state via `getDebugInfo()` when relevant.

## Commit & Pull Request Guidelines

Commits should be short, imperative sentences (`Add smart caching heuristic`, `Tighten rate limit checks`). Keep behavioural, formatting, and tooling changes in separate commits where practical. In pull requests, outline the capability touched (e.g. “Adds new trait”, “Updates generator stub”), document any newly enabled features or config knobs, and list verification commands (`composer lint`, `composer test`, extra manual checks). Surface breaking changes or migrations explicitly and attach debug output or SQL snippets if they inform reviewers.
