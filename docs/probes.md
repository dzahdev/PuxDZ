# Probes A-U

Observations from a probe script run on PHP 8.3.6 against upstream HEAD 30bc692 (with the
third-party packages of that commit installed), recorded during the analysis for the PHP 8.2
port. Each row is a starting point for one red -> green cycle; registry IDs refer to the defect
registry [defect-registry.md](defect-registry.md). "Test" names the Stage 2 test that already
specifies the row, if any; a probe without a test becomes a red test at the start of its own
cycle (ADR 0002).

Between Stage 0 and Stage 1 the third-party packages were gone and every call that loaded
`Pux\RouteRequest` or `Pux\Controller\Controller` failed earlier with a class-not-found error.
Stage 1 replaced them with `Pux\RouteRequest` without a parent and `Pux\Handler`. The "observed"
column is the behavior at 30bc692 with the packages present.

Final state (Stage 6, release 3.0.0): every probe is closed. The "Commit" column names the
commit that closed the registry ID of the row; the test in the "Test" column passes on PHP 8.2,
8.3, 8.4 and 8.5. Closed in Stage 1: L1 (probe A), L2 (probe G), L12 (probe R). Closed in
Stage 3: R1 (probe B), R2 (probe H), R4 (probe O), R5 (probe P), R6 (probe L). Closed in
Stage 4a: L3 (probe D), L4 (probe E and the CLI row below), L8 (probe I), L9 (probe N), L10
(probe C), L11 (probe Q), L13 (probe T), L24 (probe J and the static route row below). Closed in
Stage 4b: L5 (probe F), L6 (probe K), L7 (probe M), L14 (probe U). Closed in Stage 5: P3 (probe
S). Defects found after the probes (L15-L40 except L31) are listed with their tests and
commits in [defect-registry.md](defect-registry.md).

| Probe | Seam | Input | Observed at 30bc692 | Expected | ID | Test | Commit |
|---|---|---|---|---|---|---|---|
| A | RouteRequest | `RouteRequest::create('GET', '/foo', ['HTTP_HOST' => 'example.com'])`, then `pathMatch('#^/bar$#')`, `hostMatch('#^nothing$#')`, `pathLike('zzz')` | all three return `true` | all three return `false` | L1 | `RouteRequest/PredicateTest`: `test_path_match_with_non_matching_regex_returns_false`, `test_host_match_with_non_matching_regex_returns_false`, `test_path_like_with_text_absent_from_path_returns_false` | 44c6f79 |
| B | Dispatch | `mount('/api', function (Mux $s) { $s->get('/x', ...); })` | `TypeError`: argument #2 must be `Mux\|Controller`, `Closure` given | 1 route mounted | R1 | `Dispatch/MountTest`: `test_mount_of_closure_that_fills_given_mux_adds_its_routes` and two more | 62cb316 |
| C | Export | `$sub->any('/bar', ...)`, `mount('/foo', $sub)`, `eval('return ' . export() . ';')`, `dispatch('/foo/bar')` | warning `var_export does not handle circular references`, parent written as `NULL`; the evaluated Mux still dispatches `/foo/bar` | no warning, no submux object in the export | L10 | `Export/ExportRoundTripTest::test_export_after_mount_yields_mux_dispatching_mounted_route_without_warning` | c48f139 |
| D | Dispatch | `get('/s', ..., ['secure' => true])`, `dispatch('/s')` with `$_SERVER['HTTPS'] = 'on'`, then `''` | `null` with HTTPS, route without HTTPS | route with HTTPS, `null` without | L3 | `Dispatch/RequestConstraintTest`: `test_dispatch_with_secure_route_rejects_plain_http`, `test_dispatch_with_secure_route_accepts_https` | 4d39e6c |
| E | Dispatch | `post('/p', ...)`, `dispatchRequest(new RouteRequest('POST', '/p'))` with `$_SERVER['REQUEST_METHOD'] = 'GET'` | `null`: the method is read from `$_SERVER`, not from the request | the POST route | L4 | `Dispatch/RequestConstraintTest::test_dispatch_request_with_post_method_returns_post_route` | a50a0f3 |
| F | RouteExecutor | `get('/c/:id', function ($id) { return "closure $id"; })`, `execute(dispatch('/c/5'))` | `Error: Cannot use object of type Closure as array` | `"closure 5"` | L5 | `RouteExecutor/ExecuteTest::test_execute_closure_callback_returns_its_result` | 86f6173 |
| G | RouteRequest | `matchConstraints([['path' => '/nope'], ['path' => '/foo']])` and `matchConstraints([['path' => '/nope']])` on path `/foo` | `true` for both: the keys are read from the outer array, so no constraint is checked | `true`, then `false` | L2 | `RouteRequest/PredicateTest::test_match_constraints_with_only_non_matching_alternative_returns_false` | b7f4c3f |
| H | RouteExecutor (`Controller::call`) | controller with `helloAction($name)`, environment `pux.controller_action = helloAction`, route without `vars` | `ArgumentCountError` | exception that names the missing parameter `name` | R2 | `RouteExecutor/ControllerCallTest::test_call_without_variable_for_required_parameter_throws_exception_naming_parameter` | 00a6f63 |
| I | PatternCompiler | `compile('/a(/:b)/c(/:d)')`, match `/a/1/c/2` and `/a/c` | 2 tokens (the greedy group joins both optional groups), neither path matches | both match; variables `b`, `d` | L8 | `PatternCompiler/CompileTest`: `test_compile_with_two_optional_groups_matches_both_given`, `test_compile_with_two_optional_groups_matches_both_omitted` | 3f56963 |
| J | Url | `get('/blog/:year/:month(/:slug)', ..., ['id' => 'post'])`, `url('post', ['year' => 2024, 'month' => 5])`, then with `'slug' => 'x'` | `/blog/2024/5`, `/blog/2024/5/x` | same | L24 (works; untested upstream) | `Url/UrlTest`: `test_url_replaces_variables_and_drops_omitted_optional_segment`, `test_url_fills_optional_segment_when_its_variable_is_given` | 8af6faa |
| K | RouteExecutor | `get('/k(/:id)', [PC, 'item'], ['default' => ['id' => '0']])` with `item($id, $fmt = 'html')`, `execute(dispatch('/k'))` | `ArgumentCountError`: the falsy default `'0'` is dropped | `"item=0 fmt=html"` | L6 | `RouteExecutor/ExecuteTest::test_execute_uses_falsy_route_default_when_optional_variable_is_absent` | 4f64ecc |
| L | none (`Mux::sort_routes`) | `usort` with `Mux::sort_routes` on three static routes | comparator returns `bool` for PCRE pairs (deprecated in PHP 8); the method is not used by `Mux::sort()` | method removed | R6 | none: `Mux::sort_routes` is not a seam (ADR 0002); `Mux::sort()` order is specified in `Dispatch/RouteOrderTest` | 332613e |
| M | RouteExecutor | `get('/s/:param1/:param2', [PC, 'two'])` with `two($a, $b)`, `execute(dispatch('/s/x/y'))` | `ArgumentCountError`; with parameters named `$param1`, `$param2` it works | `"a=x b=y"` (binding by position) | L7 | `RouteExecutor/ExecuteTest::test_execute_binds_variables_by_position_when_parameter_names_differ` | ed90f31 |
| N | PatternCompiler | `compile(':id')`, match `42` | compiled to `#^    \:id$#xs`, `42` does not match | `42` matches with `id = 42` | L9 | `PatternCompiler/CompileTest::test_compile_pattern_starting_with_variable_captures_whole_path` | 9220399 |
| O | Export (`MuxCompiler`) | `callbackValidation = true`, `merge()` of a Mux with route `['NoSuchClass', 'm']` | `Error: Class "Pux\Exception" not found` | exception `Controller NoSuchClass does not exist.` | R4 | `Export/CompileToFileTest::test_merge_with_callback_validation_rejects_route_to_unknown_controller` | f272af9 |
| P | none (`PathDispatcher`) | `(new PathDispatcher($mux))->dispatchRequest(new RouteRequest('GET', '/x'))` | `TypeError`: parameter type resolves to `Pux\Dispatcher\RouteRequest` | the route for `/x` | R5 | none: `PathDispatcher` is not a seam (ADR 0002) | 8ee1f4a |
| Q | Dispatch | `any('/d', ..., ['domain' => 'a.com'])` without `$_SERVER['HTTP_HOST']`, `dispatch('/d')` | warning `Undefined array key "HTTP_HOST"`, result `null` | `null` without warning | L11 | `Dispatch/RequestConstraintTest::test_dispatch_with_domain_route_and_no_host_returns_null_without_warning` | c330979 |
| R | RouteRequest | `RouteRequest::createFromEnv(['REQUEST_METHOD' => 'GET', 'PATH_INFO' => '/z'])` | 5 warnings `Undefined array key` for `_REQUEST`, `_GET`, `_POST`, `_COOKIE`, `_SESSION` | request for `GET /z` without warnings | L12 | `RouteRequest/PredicateTest::test_create_from_env_without_superglobal_keys_reads_path_and_method` | bca038f |
| S | Dispatch | `foreach ($mux as $route)` | iterates the routes (works) | same | P3 (return type only) | `Dispatch/StaticRouteTest::test_iterating_mux_yields_route_paths_in_insertion_order` | 8994ec9 |
| T | Dispatch | `expand = false`, `$sub->get('/item/:id', ...)`, `mount('/u/:user', $sub)`, `dispatch('/u/bob/item/7')` | `vars.id = 7`, `vars.user` missing | `vars.id = 7`, `vars.user = bob` | L13 | `Dispatch/MountWithoutExpandTest::test_dispatch_with_expand_disabled_and_pcre_mount_point_keeps_mount_variable` | 0bc47d6 |
| U | RESTfulMuxBuilder | `addResource('product', new ProductResourceController)`, `build()`, `length()` | 10 routes | 5 routes | L14 | `RESTfulMuxBuilder/BuildTest::test_build_after_add_resource_mounts_each_resource_route_once` (one-route resource: 2 instead of 1) | 8af8215 |

## Found while writing the Stage 2 suite

| Seam | Input | Observed at 02a9768, PHP 8.3.22 | Expected | ID | Commit |
|---|---|---|---|---|---|
| Url | `add('/foo', ..., ['id' => 'foo'])`, `url('foo')` | warning `Undefined array key "pattern"` (`Mux.php:497`), result `''` | `/foo` | L24 | 8af6faa |
| Dispatch | any route with a method constraint, `dispatch($path)` in the CLI (no `$_SERVER['REQUEST_METHOD']`) | never matches: the constraint is compared with `null` | depends on the L4 fix: the method comes from the `RouteRequest` | L4 | a50a0f3 |
