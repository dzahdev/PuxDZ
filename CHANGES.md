CHANGELOG
==================

### Version 3.0.0

First release of the fork PuxDZ, package `dzahdev/puxdz`. Incompatible changes and migration
steps: [UPGRADE-3.0.md](UPGRADE-3.0.md). IDs in parentheses refer to the defect registry of the
port (R: regressions of the 2023 "PHP 8.2 update" commits, L: logic defects, P: PHP 8.2-8.5
incompatibilities); `#N` is the issue or pull request number in c9s/Pux. L31 was reserved for
#109 and not assigned: the behavior asked for there already worked.

#### Added

- `enum Pux\RequestMethod: int` with `fromName()` and `tryFromName()`; the route option
  `method` accepts a case, a name in any case or the integer value (L23).
- Attribute `#[Pux\Attribute\Route(path, method:)]` for controller actions.
- Conversion of route values to `int`, `float`, `string` and `bool` parameters when nothing is
  lost; the callbacks are called in strict types mode.
- `Pux\Handler`, the interface of `Controller::call()`, in place of `PHPSGI\App`.
- `RouteRequest::fromServer(?array $server = null)`, the only place that reads `$_SERVER`.
- Exceptions `Pux\Exception\PuxException` (interface), `PatternSyntax`, `InvalidCallback`,
  `MissingArgument`, `CacheWriteFailed` and `RouteNotFound`.
- `README.md` for 3.0, `UPGRADE-3.0.md`, `GLOSSARY.md` and the decision records in `docs/adr/`.
- A PHPUnit 11 suite at the seams of `docs/adr/0002-tdd-at-seams.md`; it also specifies the
  behavior asked for in #30 (exact static paths), #49 (the route `/`), #64 (static routes
  under a mount pattern with variables), #69 (mount options merged into the routes), #89
  (headers from the `HTTP_*` keys) and #109 (catch-all variable through `require`).
- Runnable examples in `eg/` with expected output, `bin/run-examples`,
  `bin/check-readme-examples`, `bin/test-in-docker`, PHPStan level 9 on `src`, PHP-CS-Fixer,
  GitHub Actions on PHP 8.2, 8.3, 8.4 and 8.5.

#### Changed

- Package name `dzahdev/puxdz`; `php: ^8.2` and `ext-pcre` are the only requirements.
- `RouteRequest` has no parent class and `readonly` properties (P4).
- `declare(strict_types=1)`, typed properties, parameters and return values in every file;
  `Mux`, `MuxCompiler`, `RouteExecutor`, `RouteRequest`, `PatternCompiler`,
  `ControllerRouteBuilder` and `RESTfulMuxBuilder` are `final` (P6).
- `PatternCompiler` is a scanner that tracks parenthesis depth (L8, L9).
- `dispatch()` without a `RouteRequest` builds one with `RouteRequest::fromServer()` only when a
  route with a `method`, `domain` or `secure` option matches the path (L4).
- Static routes are compared with `===` (#30).
- `Mux::export()` writes the state array explicitly with the keys `id`, `routes`, `routesById`
  and `submux` (L10).
- `Mux::compile()` and `MuxCompiler::compile()` return `void` (L36).
- `Mux::getIterator()` declares the return type `ArrayIterator` (P3).

#### Fixed

- `mount()` accepts `Closure` and callable targets again, as documented for #63 (R1).
- `Controller::call()` reads route defaults from the matched route (R2).
- `Controller::runAction()` throws `InvalidCallback` instead of `Error: Class
  "Pux\Controller\Exception" not found` (R3).
- `MuxCompiler` callback validation throws `InvalidCallback` instead of `Error: Class
  "Pux\Exception" not found` (R4).
- `pathMatch()`, `hostMatch()`, `queryStringMatch()` and `pathLike()` return `false` when the
  expression does not match (L1).
- `matchConstraints()` checks the keys of each alternative (L2).
- A route with `'secure' => true` requires HTTPS; the condition was inverted (L3, #102).
- `dispatch()` passes its `RouteRequest` to `match()` and to the submux, so the method of the
  request is used instead of `$_SERVER` (L4).
- `RouteExecutor::execute()` runs closures, function names, `[object, method]` and invokable
  objects (L5).
- Route defaults with falsy values (`'0'`, `''`, `0`, `false`) are bound (L6).
- Action arguments are bound by name, then by position, then by route default, then by
  parameter default; a missing one throws `MissingArgument` (L7, #112).
- Two optional groups in one pattern are compiled as two groups (L8).
- A variable at the start of a pattern or without a separator before it is recognized (L9).
- `export()` after `mount()` no longer writes a circular reference as `NULL` (L10).
- The `domain` and `secure` constraints read the host and HTTPS from the `RouteRequest`, without
  warnings for missing keys (L11).
- `createFromEnv()` treats the superglobal keys as optional (L12).
- With `$expand = false`, the variables of a mount pattern reach the submux route (L13).
- `RESTfulMuxBuilder::addResource()` registers and `build()` mounts once; routes were mounted
  twice (L14).
- `pathEndWith()` compares the end of the path with `str_ends_with()` (L15).
- `MuxCompiler::load()` returns the merged `Mux` instead of `true` (L17).
- `MuxCompiler` callback validation does not create controllers (L18).
- `Expandable::expand()` declares `$dynamic = false`, as its implementations do (L19).
- The docblock of `Mux::$expand` describes the default `true` (L20).
- `Mux::__set_state()` reads `id`, `routesById` and `submux` as optional keys (L21).
- `url()` of a static named route returns its path instead of a warning and `''` (L24).
- `RouteRequest::isOneOfHosts()` no longer calls a missing method (L25).
- `queryStringMatch()` without `QUERY_STRING` returns `false` without a warning (L26).
- `portEqual()` without `SERVER_PORT` returns `false` instead of `null` (L27).
- An invalid regular expression in a `RouteRequest` predicate or in `require` throws
  `PatternSyntax` instead of a warning and "no match" (L28).
- White space in pattern text is matched literally (L29).
- `hostMatch()` and `queryStringMatch()` throw `PatternSyntax` for an invalid expression also
  when the subject is absent (L30).
- `createFromEnv()` takes the path of `REQUEST_URI` without the query string (L32, #103).
- A second `RESTfulMuxBuilder::build()` returns the same `Mux` without mounting again;
  `addResource()` after `build()` throws `LogicException` (L33).
- Callback validation checks `[object, method]` callbacks instead of failing with `TypeError`
  (L34).
- `Mux::add()` stores a `'Class::method'` callback as `[class, method]` (L35).
- `compile()` throws `CacheWriteFailed` when the cache file cannot be written (L36).
- `dispatch()` throws `PatternSyntax` when a route expression fails at match time instead of
  skipping the route (L37).
- The variables of an optional group that is not in the path are left out of `vars` also when
  a later group matched, so their route defaults are bound (L38).
- `RESTfulMuxBuilder::build()` drops a trailing `/` of the prefix, so the default prefix `/`
  mounts resources at `/<id>` instead of `//<id>` (L39).
- `url()` of an unknown id throws `Pux\Exception\RouteNotFound`, which extends
  `\RuntimeException`, instead of a plain `\RuntimeException` (L40).
- `match()` and `dispatch()` declare `?RouteRequest` explicitly; the implicit nullable
  parameter was deprecated in PHP 8.4 (P1).
- `RESTfulMuxBuilder::__construct(?Mux $mux = null, array $options = [])` has no reference
  parameter and no implicit nullable type (P2).

#### Removed

- The C extension `ext/`, the phar `pux`, `phprelease.ini`, `scripts/`, `benchmarks/` and the
  dependencies `universal/universal`, `phpsgi/phpsgi`, `corneltek/phpunit-testmore`.
- `Pux\Dispatcher\PathDispatcher`, whose `dispatchRequest()` failed with `TypeError` (R5).
- `Mux::sort_routes()`, a dead copy of `MuxCompiler::sort_routes()` with a `bool` comparator
  (R6).
- `MuxCompiler::compileReflectionParameters()`, whose result was never stored (L16).
- `Pux\Testing\MuxTestCase` and `Pux\Testing\Utils` from the package autoload (P5).
- `Mux::setParent()`, `Mux::$staticRoutes`, `PatternCompiler::splitTokens()` and the
  `options['mux']` entry of mount routes.

#### Deprecated

- The global constants `REQUEST_METHOD_*`, `Mux::convertRequestMethodConstant()` (L23); removed
  in 4.0.
- The docblock annotations `@Route` and `@Method`; removed in 4.0.
- `RESTfulController::code()` and the `code*()` methods, which now call
  `http_response_code()` (L22, #55); removed in 4.0.

### Version 2.0.1
- Php 8.2+ support added.

### Version 2.0

New features:

- Added universal/universal package.
- Added Pux/RouteRequest class for dispatching routes with request constraints.
- Added `Pux\Dispatchable` interface.

Incompatible changes:

- Renamed Executor => RouteExecutor
- Removed expand property
- Moved `Pux\Controller` to `Pux\Controller\Controller`.
- Moved mux expanding logics `expand()` from `Pux\Controller` to `Pux\Controller\ExpandableController`.

Fixes:

- Fixed pattern compiler for post-separators. e.g. `/:year-:month` or `/blog/:id(.:format)`
- Fixed action method annotation parser

Deprecation:

- Disabled `Mux->expand` property to enhance submux dispatching.



### Version 1.5.0

- The controller method getActionMethods() now returns an associative array with "methodName" => [ annotation, meta ]
- The controller method getActionRoutes()  now returns an indexed array for the [ "route path", {route options} ]
- Annotations from parent class methods are now parsed when calling `getActionMethods` or `getActionRoutes`
- When the child method has no annotation, we parse the parent method
  annotation for it. unless the child method has its own annotations.

### Version 1.4.0

- Add `pux_persistent_dispatch` function for persistent dispatching.
- Dispatch performance optimization, especially `__set_state` method call, 1.5x faster than before.

### Version 1.3.3

- Fix dispatch rtrim bug for pure PHP version.

### Version 1.3.2

- phar file re-compiled.

### Version 1.3.1

- dispatch performance improvement.
- `__set_state` construction time improvement.
- fix various bugs for zts (zend thread safe mode enabled)
