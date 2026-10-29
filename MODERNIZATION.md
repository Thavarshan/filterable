# Modernization implementation and verification plan

All work is on `modernization/attribute-first`, based on current `origin/main`.

| Issue | Implementation | Verification |
| --- | --- | --- |
| #40 | Separate raw snapshot and effective inputs, explicit empty semantics, programmatic precedence, reset | Real requests, SQL/bindings/records, denied/transformed values, zero/false, reuse |
| #37 | Apply always mutates builder without execution; cache only terminal results/counts; query identity and tag capabilities | Hits/misses, no queries during apply, disabled store, distinct predicates/models/connections |
| #48 baseline | PR matrix, supported combinations, working Larastan configuration, strict lint | Install, lint, static analysis, full suite, actual service jobs |
| #41 | Explicit AsFilter, immutable cached method definitions, inheritance and legacy conflict validation | Invalid definitions fail before mutation, traits/overrides/helpers, metadata reset and benchmark |
| #42 | Typed class feature/cache/limit defaults and precedence | Config/property/inherited/concrete/runtime defaults, invalid definitions, no metadata I/O |
| #43 | Method rules/transforms; runtime factories; isolated PHP 8.5 callable fixture | Typed conversion, validation before predicates, disabled flags, cross-runtime equivalence |
| #44 | Permission/complexity metadata, explicit actor/Gate adapter, skip/reject | Allow/deny/guest/all/any, array weights, diagnostics and cache isolation |
| #45 | Optional native scope trait and invokable query adapter, current request binding | Legacy/native/callback SQL and rows, routed controllers, consecutive worker requests |
| #46 | Verified Override hooks, targeted NoDiscard, generator cursor correction | Real cursor, reflected hooks on supported frameworks, isolated PHP 8.5 diagnostics |
| #47 | Optional vector recipe with application-selected column and embedding context | PostgreSQL/pgvector ranking, limits, ordinary predicates, invalid input; no AI calls |
| #48 delivery | Attribute generator variants, migration/runtime/precedence documentation | Generated classes loaded and executed, custom stubs/force, service CI |
| #49 | Review every acceptance criterion; single PR, green exact-head CI, merge and release | Verify main checks, release target, Packagist version/source reference |

Release gate: no issue closing reference until its acceptance criteria are verified.
Retain PHP 8.3+ and Laravel 11–13; Laravel 13 itself requires PHP 8.3+.
Existing release tags omit `v`, while release titles use `v` (latest `2.1.2`).
Release automation owns CHANGELOG updates after publication; avoid a competing release entry.

## Acceptance review

- Source and tests pass maximum-level Larastan with no baseline or ignored-error list. All 1,100+ findings exposed by the corrected original configuration were resolved.
- Local strict Duster/syntax checks pass. The complete suite currently has 205 tests / 587 assertions on PHP 8.3, with the three version/service tests intentionally delegated to their runtime/service CI jobs.
- CI verified all nine PHP/framework combinations, lowest dependencies, native PHP 8.5 callable/NoDiscard fixtures, MySQL/Redis, and PostgreSQL/pgvector/Redis at `61ef37e`; final-head checks are reviewed separately before merge.
- Handler, inheritance, trait, alias, invalid-definition, helper exclusion, runtime overrides, typed Carbon conversion, Gate guest/all/any/reject, zero/false, reset, custom generated stubs/force and vector bounds have dedicated integration coverage.
- The version is 3.0.0 because explicit programmatic registration, guest authorization, fail-fast definitions/security and the protected cache hook intentionally change extension behavior. Runtime floors remain unchanged.
- Documentation lives in `docs/attributes-and-migration.md` and `docs/vector-search.md`; README cache injection/execution guidance is corrected. Release automation continues to own CHANGELOG entries.
- Final delivery evidence is the PR checks, merged-main checks, GitHub release target and Packagist source reference. Do not mark publication complete before checking them.
