# ADR 0002: Test-driven development at seams

## Status

Accepted, 2026-10-23. The list of seams is confirmed by the repository owner.

## Context

The 2023 "PHP 8.2 update" commits were automated refactorings merged without running tests
(CI had been removed) and introduced six regressions (R1-R6). The inherited suite is coupled to
implementation details: `@depends` passes `Mux` objects between tests, `$_SERVER` is written as
a side channel instead of passing a `RouteRequest`, assertions such as `ok($mux)` name no
behavior, and some expected values are computed by the same expression as the code under test.

## Decision

1. **Tests live only at seams.** A seam is a public interface whose observable result a test
   asserts on, using real `Pux\*` objects. The seams are:

   | Seam | Catches | Does not catch |
   |---|---|---|
   | `Mux::dispatch(string $path, RouteRequest $req): ?array` plus `get/post/.../any/mount` as setup | all matching: static and PCRE routes, method, domain, HTTPS, mounting, submux, order after `sort()` (L3, L4, L11, L13, R1) | details of the regular expression; pattern compilation errors before dispatch |
   | `PatternCompiler::compile(string $pattern, array $options): array`, checked through `preg_match` on the result and the `variables` list | pattern syntax: required and optional segments, `require`, `default`, separators, variable at the start (L8, L9) | how the pattern is used by `Mux` |
   | `RouteExecutor::execute(array $route): mixed` | every callback kind, argument binding by name and by position, default values, `MissingArgument` (L5, L6, L7, R2) | what the controller does internally |
   | `RouteRequest` factories `fromServer`, `fromEnv` and matchers `pathMatch`, `hostMatch`, `matchConstraints` and the others | environment parsing and predicates (L1, L2, L12, L15) | interaction with `Mux` (that is the `dispatch` seam) |
   | `ControllerRouteBuilder::build(object\|string $controller): array` | expansion of `*Action` methods, annotations and attributes, annotation inheritance, `indexAction` -> `''` | mounting of the result |
   | `RESTfulMuxBuilder::addResource` + `build(): Mux`, checked through `dispatch` on the result | the set of RESTful routes, double mounting (L14) | - |
   | `Mux::export()` -> `eval` -> `dispatch` (round trip) and `MuxCompiler::compile(path)` -> `require` -> `dispatch` | completeness of the route cache, submux in the cache (L10, L21) | the generated code as text |
   | `Mux::url(string $id, array $params): string` | URL generation from a named route (L24) | - |

2. **Not seams.** No test is written against `Mux::match`, `Mux::$routes` as an array,
   `PatternCompiler::splitTokens`, `ControllerRouteBuilder::parseMethodAnnotation`,
   `ArgumentResolver` as a separate class (it is covered through `RouteExecutor::execute` and
   `Controller::call`), or any `protected` method.

3. **Red before green.** No change to `src/` is committed without a test that failed before the
   change and passes after it. A test that fails because of a registry defect is kept red with
   the defect ID in its docblock until the cycle that fixes it.

4. **One ID per commit.** One defect or one feature is one cycle: failing test on a seam,
   minimal change to `src/`, full `composer test` green, commit naming the registry ID. A commit
   closes exactly one ID. Writing all tests first and all fixes afterwards is not allowed.

5. **Refactoring outside the loop.** Typing (`declare(strict_types=1)`, property and signature
   types), extraction of shared code and idiom updates happen in a module review after its
   tests are green, and change no test. A refactoring that requires a test change is a behavior
   change and gets its own red -> green cycle.

6. **Naming.** Tests are placed in `tests/<Seam>/<Behavior>Test.php`; methods are named
   `test_<subject>_<condition>_<result>` so that `--testdox` output reads as a specification.

## Consequences

- The inherited tests are rewritten onto these seams in Stage 2 before any change to `src/`.
- Internal structure of a module can be rewritten freely while its seam tests pass.
- Behavior that is not observable through a seam is not specified by tests; adding a seam
  requires amending this ADR.
- The probe scripts from the analysis (A-U) are kept as `docs/probes.md`, not as tests; each one
  becomes a red test at the start of its own cycle.
