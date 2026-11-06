# Contributing to Filterable

Thanks for helping improve Filterable. Small bug fixes, reproducible compatibility reports, documentation corrections and focused tests are all useful.

## Start with a real use case

Before proposing a new feature, check the [README](README.md), the [open issues](https://github.com/Thavarshan/filterable/issues), and [existing filters and tests](tests/). Explain the problem you are solving and why an existing filter, attribute, concern or configuration option does not cover it.

For a bug report, please include:

- Filterable, PHP and Laravel versions (supported: PHP 8.3–8.5; Laravel 11–13).
- A minimal filter class and model/query showing the failure; **use dummy data**, no credentials or customer records.
- Expected and actual behavior, error output, and whether caching, permissions, request validation or vector search is enabled.
- A failing test, if possible.

For a security issue, use the [private security guidance](.github/SECURITY.md), not a public issue.

## Development setup

```bash
git clone https://github.com/Thavarshan/filterable.git
cd filterable
composer install
composer lint
composer test
composer analyse
```

The tests use PHPUnit and Orchestra Testbench. Some cases cover Laravel framework behavior, so don't replace them with standalone mocks that hide Eloquent behavior.

A focused test can be run through `./bin/test.sh --filter=RelevantTestName`. Use `./bin/test.sh --coverage` for complex behavior. See [AGENTS.md](AGENTS.md) for source layout and conventions.

## Make a focused change

1. Work on a branch; keep one bug or capability per pull request.
2. Add or adjust an automated test that demonstrates the old problem and verifies the new behavior.
3. Follow existing `Filterable\\` namespaces, PSR-12 style and the current concern/attribute API. Avoid unrelated refactoring or new dependencies.
4. Update README examples if a public signature, option, compatibility requirement or behavior changes.
5. Run `composer lint`, `composer test` and `composer analyse` and report which checks actually passed. Never claim a run you did not perform.
6. Explain whether a change affects cached results, authorization, rate limits or query performance; include a migration note for breaking behavior.

Pull request description: **problem → minimal example → change → tests → compatibility notes**. Maintainers may ask you to narrow scope or add an edge case before merging.

## A good first contribution

- Verify one documented example on a supported Laravel/PHP combination.
- Add a regression test for a genuine edge case in filtering or validation.
- Improve an error message or clarify the correct use of a configuration option.
- Review an open issue and supply a smaller reproduction.

Please be kind and patient in reviews. Maintainer response time can vary.
