# Reconciliation of the defect registry, release 3.0.0

Check of [defect-registry.md](defect-registry.md) against the tests and the git history of the
branch `php82-port`, run in Stage 7 of the port (the reconciliation loop: list every input,
map each to its rule, fix the gaps, repeat until there are none). Range: 30bc692 (upstream
HEAD) to f3636df, PHP 8.2.35, 8.3.22, 8.4.27 and 8.5.12 in the `php:<version>-cli` images.

**Result: 52/52 registry rows covered (51 closed, L31 reserved and not assigned), 0
discrepancies.** Acceptance by the repository owner on real applications is not part of this
check.

## Loop history

| Round | Found | Fixed in |
|---|---|---|
| 1 | P1, P2 and P3 had no test that names the ID: P3 is covered by the foreach test of `Dispatch/StaticRouteTest`, P1 and P2 by the deprecation that `failOnDeprecation` turns into a failure on PHP 8.4 and 8.5 | 81ad7ed: docblocks of three existing tests name P1, P2, P3 |
| 1 | upstream PR #69 (mount options merged into the submux route options) had no test; #30, #49, #64 and #89 had tests that did not name the issue | f3636df: one new test at the Dispatch seam (green without a change to `src/`), five docblocks |
| 1 | IDs closed without a commit of their own (P1, P4, P5, P6) had no recorded decision in the task notes | decision recorded in the task notes of the port (not in the repository); the registry names the commit |
| 2 | nothing | - |

## 1. Registry coverage

Source: the docblock references `@see docs/defect-registry.md <ID>` in `tests/` and the titles
of `git log 30bc692..HEAD` that start with `<ID>:`. "Tests naming the ID" counts references (a
class docblock counts once and covers every test of the class); "passed" is read from the
JUnit log of `bin/test-in-docker --log-junit` on PHP 8.3. "decision" means the ID is closed
without a test of its own, for the reason in the registry row: dead code removed (R5, R6, L16),
no behaviour observable at a seam (L19, L20, L21, L22), typing or file moves in a module review
(P4, P5, P6, ADR 0002 decision 5). Every commit hash is checked with `git cat-file -e`.

| ID | Tests naming the ID | Of them passed (JUnit, PHP 8.3) | Commit | Registry row | Result |
|---|---|---|---|---|---|
| R1 | 3 | 3/3 | 62cb316 | yes | ok |
| R2 | 1 | 1/1 | 00a6f63 | yes | ok |
| R3 | 1 | 1/1 | fe812cd | yes | ok |
| R4 | 1 | 1/1 | f272af9 | yes | ok |
| R5 | decision | 0/0 | 8ee1f4a | yes | ok |
| R6 | decision | 0/0 | 332613e | yes | ok |
| L1 | 4 | 4/4 | 44c6f79 | yes | ok |
| L2 | 2 | 2/2 | b7f4c3f | yes | ok |
| L3 | 4 | 4/4 | 4d39e6c | yes | ok |
| L4 | 7 | 7/7 | a50a0f3 | yes | ok |
| L5 | 5 | 5/5 | 86f6173 | yes | ok |
| L6 | 1 | 1/1 | 4f64ecc | yes | ok |
| L7 | 7 | 7/7 | ed90f31 | yes | ok |
| L8 | 4 | 4/4 | 3f56963 | yes | ok |
| L9 | 3 | 3/3 | 9220399 | yes | ok |
| L10 | 3 | 3/3 | c48f139 | yes | ok |
| L11 | 3 | 3/3 | c330979 | yes | ok |
| L12 | 1 | 1/1 | bca038f | yes | ok |
| L13 | 3 | 3/3 | 0bc47d6 | yes | ok |
| L14 | 1 | 1/1 | 8af8215 | yes | ok |
| L15 | 1 | 1/1 | 7d6bc32 | yes | ok |
| L16 | decision | 0/0 | 83b4c42 | yes | ok |
| L17 | 1 | 1/1 | d53cdd7 | yes | ok |
| L18 | 2 | 2/2 | 58ef874 | yes | ok |
| L19 | decision | 0/0 | d12f369 (Stage 4b review) | yes | ok |
| L20 | decision | 0/0 | d12f369 (Stage 4b review) | yes | ok |
| L21 | decision | 0/0 | 158d1de | yes | ok |
| L22 | decision | 0/0 | d12f369 (Stage 4b review) | yes | ok |
| L23 | 1 | 6/6 | 302ab74 | yes | ok |
| L24 | 3 | 3/3 | 8af6faa | yes | ok |
| L25 | 2 | 2/2 | bbd1f15 | yes | ok |
| L26 | 1 | 1/1 | acfeecd | yes | ok |
| L27 | 1 | 1/1 | 90d3987 | yes | ok |
| L28 | 2 | 6/6 | 4ed7a11 | yes | ok |
| L29 | 3 | 3/3 | a7d9458 | yes | ok |
| L30 | 2 | 2/2 | 112cad6 | yes | ok |
| L31 | decision | 0/0 | - | yes | ok |
| L32 | 1 | 1/1 | db70cc4, 8967f42 | yes | ok |
| L33 | 2 | 2/2 | 4259d5c | yes | ok |
| L34 | 3 | 3/3 | 11bf9a8 | yes | ok |
| L35 | 2 | 2/2 | 36ac473 | yes | ok |
| L36 | 2 | 2/2 | 62bc5fa | yes | ok |
| L37 | 1 | 1/1 | 2844afa | yes | ok |
| L38 | 2 | 2/2 | 77b3f6b | yes | ok |
| L39 | 2 | 2/2 | 0f5509d | yes | ok |
| L40 | 1 | 1/1 | 93582a5 | yes | ok |
| P1 | 1 | 1/1 | a50a0f3 (closed with L4) | yes | ok |
| P2 | 1 | 1/1 | b59e606 | yes | ok |
| P3 | 1 | 1/1 | 8994ec9 | yes | ok |
| P4 | decision | 0/0 | 8bb4c55 (Stage 5 review) | yes | ok |
| P5 | decision | 0/0 | 02a9768 (Stage 0) | yes | ok |
| P6 | decision | 0/0 | 2e9e044, 8bb4c55, a3d59e1, 8ed523e, 588087d, 73370ca, fe58023, b7b4462 (Stage 5 review) | yes | ok |

Totals: 52 IDs, 92 test references, 0 IDs without a test or a recorded decision, 0 IDs without
a commit or a recorded commit, 0 named tests that did not pass. Group `known-defect`: 0 tests
(`grep -rn known-defect tests` empty, `composer test:red` prints "No tests executed!").

## 2. One ID per commit

Script over `git log 30bc692..HEAD --format='%h %s'`: a commit whose title starts with `<ID>:`
is a closing commit; it must not name a second ID.

| Measure | Value |
|---|---|
| commits in the range | 98 (before the commit of this file) |
| closing commits (title starts with an ID) | 45 |
| closing commits whose title names a second ID | 0 |
| non-closing commits whose title names several IDs | 2: 67065c8 "Stage 6: probes with closing commits, GLOSSARY for L36 and L37" and 81ad7ed "Stage 7: test docblocks name P1, P2 and P3"; both change documentation only and close nothing |
| IDs closed by a commit that is not their own | recorded: L19, L20, L22 in d12f369 (Stage 4b review), P1 in a50a0f3 (with L4, stated in its message), P4 and P6 in the Stage 5 review commits, P5 in 02a9768 (Stage 0) |
| IDs with two commits | L32: db70cc4 (fix) and 8967f42 (array initialization in its test) |

## 3. Stage 2 tests unchanged by later stages

Compared: every file of `tests/` at e100eb9 (end of Stage 2) with HEAD.

Method level: each of the 113 methods of the Stage 2 test classes (97 tests, 16 helpers and
data providers) is compared after removing docblocks, comments, attribute lines
(`#[Group('known-defect')]`) and white space differences: 112 identical, 1 changed, 0 removed.
The changed one is `Dispatch/RequestConstraintTest::test_dispatch_with_domain_route_and_no_host_returns_null_without_warning`:
c330979 (L11) passes `RouteRequest::create('GET', '/d', [])` instead of calling `dispatch('/d')`
without a request, which throws in the CLI since L4; the intent (a domain route without a host
gives `null` without a warning) is kept. Recorded in the Stage 4a notes, item 8.

Line level: every line removed from a Stage 2 file, classified as (a) docblock, comment or group
attribute, (b) formatting (the same code is added back: `cs:fix` in b7b4462, one-line method
bodies split, `declare(strict_types=1)` added to the fixtures), (c) recorded exception, (d)
unexplained.

| File | (a) | (b) | (c) | (d) |
|---|---|---|---|---|
| `tests/ControllerRouteBuilder/BuildTest.php` | 8 | 0 | 0 | 0 |
| `tests/Dispatch/ControllerMountTest.php` | 15 | 0 | 0 | 0 |
| `tests/Dispatch/MountTest.php` | 5 | 0 | 0 | 0 |
| `tests/Dispatch/MountWithoutExpandTest.php` | 5 | 0 | 0 | 0 |
| `tests/Dispatch/RequestConstraintTest.php` | 31 | 0 | 1 | 0 |
| `tests/Export/CompileToFileTest.php` | 5 | 0 | 0 | 0 |
| `tests/Export/ExportRoundTripTest.php` | 5 | 0 | 0 | 0 |
| `tests/Fixtures/AnnotatedActionsController.php` | 0 | 7 | 0 | 0 |
| `tests/Fixtures/CRUDProductController.php` | 0 | 4 | 0 | 0 |
| `tests/Fixtures/ChildController.php` | 0 | 4 | 0 | 0 |
| `tests/Fixtures/ExpandableProductController.php` | 0 | 10 | 0 | 0 |
| `tests/Fixtures/FooController.php` | 0 | 1 | 0 | 0 |
| `tests/Fixtures/PageController.php` | 1 | 3 | 0 | 0 |
| `tests/Fixtures/ParentController.php` | 0 | 4 | 0 | 0 |
| `tests/Fixtures/ProductController.php` | 0 | 4 | 0 | 0 |
| `tests/Fixtures/ProductResourceController.php` | 0 | 4 | 0 | 0 |
| `tests/PatternCompiler/CompileTest.php` | 7 | 0 | 0 | 0 |
| `tests/RESTfulMuxBuilder/BuildTest.php` | 7 | 0 | 0 | 0 |
| `tests/RouteExecutor/ControllerCallTest.php` | 9 | 0 | 0 | 0 |
| `tests/RouteExecutor/ExecuteTest.php` | 10 | 0 | 0 | 0 |
| `tests/RouteRequest/PredicateTest.php` | 29 | 0 | 0 | 0 |
| `tests/bootstrap.php` | 3 | 0 | 0 | 0 |
| total | 140 | 41 | 1 | 0 |

Commits that touched Stage 2 fixtures: b7b4462 (`cs:fix` with `declare_strict_types`, Stage 5)
and ed90f31 (L7, methods added to `ArgumentBindingController`, none removed); `tests/bootstrap.php`:
dc3b8c6 (comment) and b7b4462.

## 4. Upstream issues

| Issue | Title | Covered by |
|---|---|---|
| #30 | strcmp vs strncmp | `Dispatch/StaticRouteTest::test_dispatch_unknown_path_returns_null`, `Dispatch/HandlerRouteTest::test_dispatch_of_static_route_with_non_handler_object_requires_exact_path`; static paths compared with `===` (a3d59e1) |
| #49 | route '/' handling differs | `Dispatch/StaticRouteTest::test_dispatch_root_path_returns_root_route` |
| #55 | Use http_response_code function to output status | L22: no test (header output has no seam, ADR 0002); `code*()` call `code()`, which calls `http_response_code()`, all `@deprecated` (d12f369) |
| #63 | accept a callable to `Mux::mount()` | R1: the three tests in the registry row (62cb316) |
| #64 | static route under a mount pattern with variables | `Dispatch/MountTest::test_dispatch_after_mount_at_pcre_path_returns_static_sub_route_with_mount_variable` |
| #69 | merging options at mount | `Dispatch/MountTest::test_dispatch_after_mount_with_options_merges_them_recursively_into_submux_route_options` (f3636df), `Dispatch/MountTest::test_dispatch_after_mount_keeps_default_options_of_submux_routes` |
| #89 | HTTP_* keys as header fields | `RouteRequest/FromServerTest::test_from_server_derives_headers_from_http_and_content_keys`, `RouteRequest/CreateFromEnvTest::test_create_derives_headers_from_http_and_content_keys` |
| #90 | HTTP method overridden by HTTP_X_HTTP_METHOD_OVERRIDE | out of scope for 3.0: not implemented. The method of a route constraint is `RouteRequest::$requestMethod`, read from `REQUEST_METHOD`; an application that honours the header builds its `RouteRequest` with the overriding method |
| #98 | SAPIResponder should handle a header array | out of scope for 3.0: not implemented. `Pux\Responder\SAPIResponder` is not part of 2.0.x-dev `src/` nor of 3.0; Pux returns the callback result and writes no response, so a header list or map is the application's job |
| #102 | Fix HTTPS condition | L3: the four tests in the registry row (4d39e6c) |
| #103 | Using ? instead of / | L32: `RouteRequest/CreateFromEnvTest::test_create_from_env_takes_path_of_request_uri_without_query_string` (db70cc4) |
| #109 | Raw url with slashes | `Dispatch/PcreRouteTest::test_dispatch_catch_all_variable_with_require_captures_remaining_segments`, `test_dispatch_variable_without_require_rejects_path_with_more_segments`, `PatternCompiler/CompileTest::test_compile_variable_with_catch_all_require_captures_slashes`, `test_compile_plus_after_variable_name_is_literal_text` (7dd8a44); green without a change, L31 not assigned |
| #112 | Route parameters error | L7: the seven tests in the registry row (ed90f31) |

## 5. Architectural invariants

The invariants of the port (section 3.1 of the port plan), with the check that enforces each
one now.

| # | Invariant | Enforced by | Result |
|---|---|---|---|
| 1 | `src/` uses no class outside `Pux\*` and the PHP standard library | `grep -rhoE '^use [^;]+;' src \| grep -v 'use Pux'`; `composer.json` `require`; PHPStan level 9 (an unknown class is an error) | 14 imports, all PHP classes (`ArrayIterator`, `Attribute`, `Closure`, `InvalidArgumentException`, `IteratorAggregate`, `LogicException`, 5 `Reflection*`, `RuntimeException`, `Throwable`, `ValueError`); `require` holds `php: ^8.2` and `ext-pcre` only |
| 2 | `match()` and `dispatch()` do not read `$_SERVER` | `grep -rn '\$_SERVER' src` | one read: `RouteRequest::fromServer()` (`$server ??= $_SERVER`), the other matches are docblocks; tests pass `RouteRequest` objects (`grep -rn '\$_SERVER' tests` empty) |
| 3 | route structure `[pcre, pattern, callback, options]`, no objects in `options`, no `parent` in the export | `Export/ExportRoundTripTest::test_dispatch_after_mount_returns_route_without_submux_object_in_options` and the two other L10 tests; PHPStan type `Route` of `Mux` | pass on 8.2-8.5; `Mux` has no `parent` property |
| 4 | a routing or execution error is an exception of `Pux\Exception\*` | the tests of R3, R4, L5, L7, L8, L28, L30, L36, L37, L40 expect `InvalidCallback`, `MissingArgument`, `PatternSyntax`, `CacheWriteFailed`, `RouteNotFound`; `grep -rhoE 'throw new [\\A-Za-z]+' src` | pass. The standard exceptions left (`\InvalidArgumentException` 12, `\LogicException` 8, `\ValueError` 2, `\RuntimeException` 2, `\UnexpectedValueException` 1) report invalid arguments or configuration, as listed in the README; the two `\RuntimeException` in `url()` report a `preg_replace` failure on fixed expressions. A path without a route returns `null` from `dispatch()` by design |
| 5 | no change to `src/` without a test that failed before it; each ID named by its test | check 1 (92 references, 0 gaps); 65 commits touched `src/` after e100eb9: 45 with test changes in the same commit, 20 without (Stage 1 to 5 review commits: typing, `cs:fix`, docblocks, extraction of `ArgumentResolver`; removals R5, R6, L16; L21; P2, P3) | 0 unexplained; the behaviour changes on invalid input made in the Stage 5 review commits are listed in the Stage 5 notes, item 12 |
| 6 | typing in the review changes no test | check 3; `git show --stat` of the review commits | the Stage 5 review commits touch `tests/` only in b7b4462 (`cs:fix`) |
| 7 | system boundaries behind a seam: `$_SERVER` through `fromServer()`, `header()`/`http_response_code()` through a responder, the file system through a path argument | `grep -rnE '\bheader\(\|http_response_code\(\|file_put_contents\(' src` | `$_SERVER`: see 2; file system: `CacheFile::write($file, $code)` only, called by `Mux::compile($outFile)` and `MuxCompiler::compile($outFile)`; `header()`: none; `http_response_code()`: one call in `RESTfulController::code()`, `@deprecated` (L22): no `Pux\Responder` interface was introduced, because the methods are kept only for 2.x callers and removed in 4.0 |

## 6. Tooling gates

| Gate | PHP 8.2.35 | PHP 8.3.22 | PHP 8.4.27 | PHP 8.5.12 |
|---|---|---|---|---|
| `composer check` (cs, stan, readme, test) exit code | 0 | 0 | 0 | 0 |
| tests / assertions | 240 / 416 | 240 / 416 | 240 / 416 | 240 / 416 |
| `composer test:red` | No tests executed | No tests executed | No tests executed | No tests executed |
| `bin/run-examples` (`PHP_IMAGE=php:<v>-cli`) | 15 / 15 ok | 15 / 15 ok | 15 / 15 ok | 15 / 15 ok |

| Gate | Result |
|---|---|
| PHP-CS-Fixer | 0 of 88 files to fix |
| PHPStan | `src` level 9: no errors; `tests` level 5: no errors |
| `bin/check-readme-examples` | 13 `php` blocks match their `eg/` files |
| `composer validate --strict` (`composer:2`) | valid, exit 0 |
| characters U+0451, U+0401, U+2014, U+2013, U+00AB, U+00BB, U+2026 in `git ls-files` (125 files) and in the messages of 30bc692..HEAD | 0 |
| `git log --all --format='%B' \| grep -ciE 'co-authored\|generated with\|claude\|anthropic'` (1033 commits) | 0; author and committer of every port commit: Denis Zakharov |

## 7. Final numbers

| Measure | Value |
|---|---|
| tests / assertions | 240 / 416 |
| test classes / files in `tests/` | 27 / 48 |
| `src/` files / lines / lines without comments and blank lines | 25 / 3090 / 1569 |
| `src/` files with `declare(strict_types=1)` | 25 / 25 |
| registry rows | 52: 51 closed (R1-R6, L1-L30, L32-L40, P1-P6), L31 reserved |
| commits since 30bc692 | 98 before the commit of this file |
