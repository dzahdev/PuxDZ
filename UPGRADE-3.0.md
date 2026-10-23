# Upgrading from Pux 2.0.x-dev to PuxDZ 3.0

This file lists every incompatible change between the 2.0.x-dev branch of Pux (package
`corneltek/pux`, commit 30bc692 of 2023-03-28) and PuxDZ 3.0.0. Each entry gives the behavior
before, the behavior after and what to change in application code. The IDs in parentheses
(R1-R6, L1-L38, P1-P6) are the defect IDs listed in [CHANGES.md](CHANGES.md).

Order of work for an upgrade:

1. Replace the package and check the PHP version (section "Package and platform").
2. Pass a `RouteRequest` to every `dispatch()` call, or check the rule for dispatch without a
   request (section "Pux\Mux").
3. Replace `REQUEST_METHOD_*` constants and docblock annotations; they still work but are
   deprecated.
4. Run your tests; code that relied on a warning, `null` or `false` for an error now gets an
   exception (section "Exceptions instead of warnings and null").

## Package and platform

**Package name.**
- Before: `composer require corneltek/pux "2.0.x-dev"`.
- After: `composer require dzahdev/puxdz`. The namespace is still `Pux`.
- What to do: remove `corneltek/pux` from `composer.json` and require `dzahdev/puxdz` `^3.0`.

**PHP version.**
- Before: no `require.php`; the README named PHP 5.4.
- After: `php: ^8.2` and `ext-pcre`. CI runs PHP 8.2, 8.3, 8.4 and 8.5.
- What to do: run the application on PHP 8.2 or later.

**Third-party packages.**
- Before: `universal/universal`, `phpsgi/phpsgi` and, for tests, `corneltek/phpunit-testmore`
  were installed with Pux.
- After: no runtime dependency.
- What to do: if your code uses `Universal\*` or `PHPSGI\*` classes itself, require those
  packages in your own `composer.json`; Pux no longer installs them.

**C extension and tooling.**
- Before: `ext/` (the `pux` C extension with `pux_persistent_dispatch()`), the phar `pux`,
  `phprelease.ini`, `scripts/`, `benchmarks/`.
- After: removed. The library is PHP only.
- What to do: remove calls to `pux_persistent_dispatch()` and other extension functions; use the
  route cache (`Mux::compile()` and `require`) instead.

**Test base class (P5).**
- Before: `Pux\Testing\MuxTestCase` and `Pux\Testing\Utils` were autoloaded from `src/Testing`.
- After: removed from the package.
- What to do: extend `PHPUnit\Framework\TestCase` and build requests with
  `RouteRequest::create()` (see the README section "Testing your routes").

**Strict types, `final` classes, native types (P6).**
- Before: no `declare(strict_types=1)`, untyped properties and signatures; every class could be
  extended.
- After: every file declares strict types; `Mux`, `MuxCompiler`, `RouteExecutor`, `RouteRequest`,
  `PatternCompiler`, `ControllerRouteBuilder`, `RESTfulMuxBuilder` and `Attribute\Route` are
  `final`; properties, parameters and return values have native types.
- What to do: replace subclasses of the `final` classes with composition. A subclass of
  `Controller`, `ExpandableController` or `RESTfulController` that overrides `call()`,
  `getRequest()`, `toJson()`, `runAction()`, `forward()`, `hasAction()`, `expand()` or a `code*()`
  method, or redeclares one of their properties, must declare compatible types.

## Removed classes and methods

| Removed | Replacement |
|---|---|
| `Pux\Dispatcher\PathDispatcher` (R5; its `dispatchRequest()` failed with `TypeError`) | `Mux::dispatchRequest($request)` or `Mux::dispatch($path, $request)` |
| `Mux::sort_routes()` (R6) | `Mux::sort()`; the comparator is `MuxCompiler::sort_routes()` |
| `MuxCompiler::compileReflectionParameters()` (L16; its result was never stored) | nothing |
| `Mux::setParent()` and the parent link of a submux | nothing; the parent was written and never read |
| `Mux::$staticRoutes` (L21) | nothing; the property was never used |
| `$options['mux']` of a mount route (L10) | `Mux::getSubMux($id)` with the id in the route callback; `ExpandableController::getMux()` for a mounted controller |
| `PatternCompiler::splitTokens()` | nothing; `PatternCompiler::compile()` is the interface |
| the key `__request_object` read by `RouteRequest::createFromEnv()` | nothing; the cache never worked (the key was written to a copy of the array) |

## Pux\Mux

**`mount()` targets (R1).**
- Before: the signature was `mount($pattern, Mux|Controller $mux, array $options = [])`, so a
  closure target, documented in the README, failed with `TypeError`.
- After: `mount(string $pattern, Mux|Expandable|Closure|callable $target, array $options = [])`.
  A closure receives a new `Mux` to fill or returns the `Mux` to mount.
- What to do: nothing for `Mux` and controller targets; named arguments must use `target`.

**Dispatch reads the request from the `RouteRequest` (L4, L11).**
- Before: `dispatch($path, $request)` called `match($path)` without the request, so the method,
  host and HTTPS flag of every route were read from `$_SERVER`; `dispatchRequest()` had no effect
  on them, and `HTTP_HOST` or `HTTPS` missing from `$_SERVER` produced a warning per route.
- After: `dispatch()` and `match()` read the method, `HTTP_HOST` and `HTTPS` only from the
  `RouteRequest` and pass it to the submux. Without a request, a route without constraints
  matches without reading anything; the first route whose pattern matches and that has a
  `method`, `domain` or `secure` option makes `dispatch()` build the request with
  `RouteRequest::fromServer()`, which throws `\InvalidArgumentException` when `$_SERVER` has no
  `REQUEST_METHOD` (the command line) or no path.
- What to do: pass `RouteRequest::fromServer()` (web) or `RouteRequest::create()` (tests,
  command line) to `dispatch()`, or call `dispatchRequest()`. Code that set
  `$_SERVER['REQUEST_METHOD']` in tests to select a route must pass a request instead.

**`secure` (L3, upstream PR #102).**
- Before: the condition was inverted; a route with `'secure' => true` was rejected over HTTPS and
  accepted over plain HTTP.
- After: `'secure' => true` requires the server key `HTTPS` with a value that is not empty and
  not `off`; `false` or no option is no constraint.
- What to do: remove workarounds that set `secure` to the opposite value.

**`domain`.**
- Before: compared with `$_SERVER['HTTP_HOST']`, with a warning when it was missing.
- After: compared with `HTTP_HOST` of the request, ignoring case; a request without `HTTP_HOST`
  does not match. A `domain` value that is not a string throws `\LogicException`.
- What to do: nothing, unless the request object has no `HTTP_HOST` in its server array.

**Request method option (L23).**
- Before: the option `method` held the value of a global constant `REQUEST_METHOD_*` defined when
  `Mux.php` was loaded; `Mux::convertRequestMethodConstant()` returned `0` for an unknown name.
- After: the option accepts a `Pux\RequestMethod` case, its name in any case (`'post'`) or its
  integer value, and stores the integer. An unknown name, value or type throws `\ValueError` when
  the route is added. `convertRequestMethodConstant()` is deprecated and throws `\ValueError` for
  an unknown name.
- What to do: write `'method' => RequestMethod::POST` or `'method' => 'POST'`; replace
  `convertRequestMethodConstant($name)` with `RequestMethod::fromName($name)->value`.

**String callbacks (L35).**
- Before: `'Class:method'` was split into `[Class, method]`; `'Class::method'` was split into
  three parts and the route could not be executed.
- After: both forms are stored as `[Class, method]`.
- What to do: nothing.

**Static paths.**
- Before: a static route matched when `$route[1] == $path` (loose comparison, so `'01'` matched
  `'1'`), and a subpath `'0'` under a mount point without expansion became `''`.
- After: `===`, and the subpath is passed unchanged.
- What to do: nothing, unless routes relied on loose comparison.

**Mount without expansion and PCRE mount points (L13).**
- Before: with `$expand = false`, the variables of a mount pattern such as `/u/:user` were lost.
- After: they are added to `vars` of the submux route; the submux value wins on a name clash.
- What to do: nothing; remove code that parsed the path again to get those variables.

**Route cache format (L10, L21).**
- Before: `export()` called `var_export($this)`; after `mount()` the submux object in
  `$options['mux']` and its `parent` field caused a circular reference warning and `NULL` in the
  cache. `__set_state()` read `id` without `isset`.
- After: `export()` writes a call of `\Pux\Mux::__set_state()` with an array of the keys `id`,
  `routes`, `routesById` and `submux`; route options hold no objects. `__set_state()` requires
  `routes` and reads the other keys as optional, so cache files of 2.x load.
- What to do: regenerate cache files once with 3.0 to get the submux in them.

**`compile()` (L36).**
- Before: `compile($outFile, $sort = true)` returned the result of `file_put_contents()`, `false`
  with a warning when the file could not be written.
- After: `compile(string $outFile, bool $sortBeforeCompile = true): void` throws
  `Pux\Exception\CacheWriteFailed` (with the property `path`).
- What to do: replace `if ($mux->compile($file) === false)` with a `try`/`catch` of
  `CacheWriteFailed` where the application can act on it, or let it propagate.

**Match-time PCRE failures (L37).**
- Before: when `preg_match()` failed while dispatching (an invalid expression in a cache file, the
  backtrack limit), the route was skipped as if it did not match.
- After: `dispatch()` and `match()` throw `Pux\Exception\PatternSyntax`.
- What to do: fix the `require` expression or the cache file named in the exception.

**Variables of absent optional groups (L38).**
- Before: an optional group that was not in the path and was followed by a matched group was
  reported by `preg_match()` as the empty string: `/a(/:b)/:c` dispatched `/a/1` with
  `vars['b'] === ''`, and the route default of `b` was not bound.
- After: `vars` holds only the groups that took part in the match (`c` here), so the route
  default of `b` is bound. A variable whose group matched the empty string keeps `''`.
- What to do: replace checks for `$vars['b'] === ''` with `isset($vars['b'])` or a route default.

**`url()` (L24).**
- Before: a static named route produced the warning `Undefined array key "pattern"` and returned
  `''`.
- After: a static named route returns its path. Parameters are converted to strings and inserted
  without encoding; an unknown id throws `\RuntimeException` (as before).
- What to do: encode parameter values with `rawurlencode()` before passing them.

**`getIterator()` (P3).**
- Before: `#[\ReturnTypeWillChange]` without a return type.
- After: `getIterator(): ArrayIterator`.
- What to do: nothing.

## Global constants and Pux\RequestMethod (L23)

- Before: `Mux.php` called `define('REQUEST_METHOD_GET', 1)` and six more when it was loaded.
- After: `enum Pux\RequestMethod: int` (`GET = 1`, `POST = 2`, `PUT = 3`, `DELETE = 4`,
  `PATCH = 5`, `HEAD = 6`, `OPTIONS = 7`) with `fromName()` and `tryFromName()`. The constants
  `REQUEST_METHOD_GET` to `REQUEST_METHOD_OPTIONS` are defined from the enum values in
  `src/constants.php` (Composer `autoload.files`), each guarded by `defined()`, and are deprecated;
  they are removed in 4.0.
- What to do: replace `REQUEST_METHOD_POST` with `RequestMethod::POST` or
  `RequestMethod::POST->value`.

## Pux\RouteRequest

**No parent class.**
- Before: `RouteRequest extends Universal\Http\HttpRequest`, with the methods and the
  `ArrayAccess` of that class (four deprecations on PHP 8.3).
- After: a standalone `final` class. The property names of `HttpRequest` are kept:
  `requestMethod`, `path`, `serverParameters`, `parameters`, `queryParameters`,
  `bodyParameters`, `cookieParameters`, `sessionParameters`, `headers`.
- What to do: replace calls to inherited `HttpRequest` methods and array access with the
  properties.

**Readonly properties and constructor (P4).**
- Before: `new RouteRequest($method, $path)` with public untyped properties set afterwards.
- After: every property is `readonly`; the constructor is `new RouteRequest(string $method,
  string $path, array $server = [], array $parameters = [], array $query = [], array $body = [],
  array $cookies = [], array $session = [])`; `headers` is derived from the server array.
- What to do: pass the arrays to the constructor or use a factory; do not assign properties.

**Factories.**
- Before: `create()` and `createFromEnv()`; headers came from `getallheaders()` when it existed.
- After:
  - `fromServer(?array $server = null)` is new: it reads `$server` or `$_SERVER`, requires
    `REQUEST_METHOD` and `PATH_INFO` or `REQUEST_URI`, and throws `\InvalidArgumentException`
    otherwise. It is the only place where the library reads `$_SERVER`.
  - `createFromEnv()` treats `_REQUEST`, `_GET`, `_POST`, `_COOKIE`, `_SESSION` as optional (L12;
    before: five warnings), takes the path of `REQUEST_URI` without the query string (L32,
    upstream issue #103; before: `/tools?p=1` did not match `/tools`) and throws
    `\InvalidArgumentException` when there is no path (before: `'/'`).
  - `headers` is built from the `HTTP_*`, `CONTENT_TYPE` and `CONTENT_LENGTH` keys of the server
    array (upstream issue #89); `getallheaders()` is not called.
  - A value of the server array read by a factory or predicate must be a scalar; otherwise
    `\InvalidArgumentException`.
- What to do: build requests with `fromServer()` in front controllers and with `create()` in
  tests.

**Predicates (L1, L2, L15, L25, L26, L27, L28, L30).**
- Before: `pathMatch()`, `hostMatch()`, `queryStringMatch()` and `pathLike()` returned `true` when
  the expression did not match; `matchConstraints()` ignored the keys of each alternative;
  `isOneOfHosts()` called a missing method; `queryStringMatch()` without `QUERY_STRING` warned;
  `portEqual()` without `SERVER_PORT` returned `null`; an invalid expression produced a warning
  and the answer "no match".
- After: the predicates return `false` when the subject does not match or is absent; an invalid
  expression throws `Pux\Exception\PatternSyntax`, also when the subject is absent;
  `matchConstraints()` returns `true` when every key of one alternative matches;
  `pathEndWith()` uses `str_ends_with()`.
- What to do: remove workarounds for the inverted answers.

## Pux\Handler instead of PHPSGI\App

- Before: `Pux\Controller\Controller implements PHPSGI\App`.
- After: `interface Pux\Handler` with the same method `call(array &$environment, array
  $response)`; `Controller` implements it. A static route whose callback is a `Handler` matches
  by path prefix, as before for `PHPSGI\App`.
- What to do: replace `implements PHPSGI\App` with `implements Pux\Handler` and type checks
  against `PHPSGI\App` with `Pux\Handler`.

## Pux\PatternCompiler

- Before: `splitTokens()` split the pattern with regular expressions. Two optional groups were
  joined into one (L8), a variable at the start of the pattern was text (L9), the `x` flag dropped
  literal white space (L29), and an unbalanced parenthesis compiled into a broken expression.
- After: a scanner that tracks parenthesis depth. Groups may follow one another and nest, a
  variable needs no separator before it, white space is matched literally, `+` after a variable
  name is literal text, and an unbalanced parenthesis or a `require` expression that makes the
  pattern invalid throws `PatternSyntax` when the route is added (L28).
- What to do: check patterns with spaces or with `+` after a name; write a catch-all variable as
  `'require' => ['name' => '.+']` (upstream issue #109).

## Pux\RouteExecutor and argument binding

**Callbacks (L5).**
- Before: `execute()` called `new ReflectionClass($callback[0])`, so closures and function names
  failed; a missing action threw `\LogicException`.
- After: closures, function names, `[class, method]`, `[object, method]` and invokable objects
  are executed; a missing class or method or a value that is not callable throws
  `Pux\Exception\InvalidCallback`.
- What to do: catch `InvalidCallback` (or `PuxException`) instead of `\LogicException`.

**Binding (L6, L7, upstream issue #112).**
- Before: arguments were bound by parameter name only; a route default with a falsy value (`'0'`,
  `''`) was dropped; a missing required parameter threw `\Exception('parameter is not
  defined.')`, and parameters with other names than the variables got no argument.
- After: by name, then by position among the named variables not taken by name, then the route
  `default` (falsy values included), then the parameter default; otherwise
  `Pux\Exception\MissingArgument`. Arguments are passed by name. The same rule applies to
  `Controller::call()` and `Controller::runAction()`.
- What to do: catch `MissingArgument` instead of `\Exception`. Check actions whose parameter names
  differ from the variable names: they now receive the values by position.

**Scalar conversion.**
- Before: the callback was called by `call_user_func_array()` in coercive mode: `"false"` and
  `"off"` for a `bool` parameter gave `true`, `"2.5"` for an `int` parameter gave `2` with a
  deprecation.
- After: the callback is called in strict types mode, and a value for an `int`, `float`, `string`
  or `bool` parameter is converted only when nothing is lost (`"7"` to `7`, `"off"` to `false`);
  any other value throws `\TypeError`.
- What to do: add a `require` expression (`\d+`) to variables bound to `int` parameters, or
  declare the parameter as `string`.

## Pux\Controller\Controller, ExpandableController, RESTfulController

- `call()` throws `InvalidCallback` for a missing action (before: `ReflectionException`; R2:
  the `default` branch read an undefined variable) and `\InvalidArgumentException` for an
  environment without `pux.controller_action` or with a `pux.route` that is not a route.
- `runAction()` throws `InvalidCallback` for a missing action (R3; before: `Error: Class
  "Pux\Controller\Exception" not found`) and `MissingArgument` for an unbound required
  parameter (before: the call failed with `ArgumentCountError`).
- `forward()` checks that the class exists and is a `Controller`.
- `toJson()` throws `\JsonException` for data that cannot be encoded (before: the body was
  `false`).
- `getRequest()` builds the request with `RouteRequest::createFromEnv()` (before:
  `HttpRequest::createFromGlobals()`).
- `hasAction(): string|false` returns the action method name or `false`.
- `ExpandableController::getMux()` returns the `Mux` of the last `expand()`.
- `Expandable::expand(array $options = [], bool $dynamic = false)`: the interface default of
  `$dynamic` is now `false`, as in the implementations (L19).
- `RESTfulController::code()`, `codeOk()`, `codeCreated()`, `codeAccepted()`,
  `codeNoContent()`, `codeBadRequest()`, `codeForbidden()` and `codeNotFound()` call
  `http_response_code()` (before: each method called `header()` with an `HTTP/1.1` status line)
  and are deprecated;
  they are removed in 4.0 (L22, upstream issue #55). Return `[code, headers, body]` from the
  action instead.

## Pux\Builder\ControllerRouteBuilder and route attributes

- Before: routes of `*Action` methods were read from the docblock lines `@Route("/path")` and
  `@Method("GET")`.
- After: the attribute `#[Pux\Attribute\Route(string $path, ?RequestMethod $method = null)]` sets
  the path and the method. The docblock annotations are still read for a method without the
  attribute; they are deprecated and removed in 4.0. When a method has the attribute, its docblock
  is not read at all.
- What to do: replace the annotations with the attribute, and import `Pux\Attribute\Route` and
  `Pux\RequestMethod`:

```php
// 2.x
/**
 * @Route("/items/:id")
 * @Method("GET")
 */
public function itemAction($id)

// 3.0
#[Route('/items/:id', method: RequestMethod::GET)]
public function itemAction($id)
```

## Pux\MuxBuilder\RESTfulMuxBuilder

- `addResource()` registers the resource and `build()` mounts it (L14; before: `addResource()`
  mounted at once and `build()` mounted everything again, so every route existed twice).
- A second `build()` returns the same `Mux` without mounting again; `addResource()` after
  `build()` throws `\LogicException` (L33).
- The constructor is `__construct(?Mux $mux = null, array $options = [])` without a reference
  parameter (P2; before: `Mux & $mux = null`, an implicitly nullable parameter deprecated in
  PHP 8.4).
- What to do: call `build()` once after all `addResource()` calls; remove code that removed
  duplicate routes.

## Pux\MuxCompiler

- `load()` returns the merged `Mux` (L17; before: `true`) and throws
  `\UnexpectedValueException` when the file does not return a `Mux`.
- With `$callbackValidation = true`, `merge()` checks callbacks without creating the controller
  (L18; before: the constructor ran during compilation), checks `[object, method]` callbacks
  (L34; before: `TypeError`) and throws `InvalidCallback` (R4; before: `Error: Class
  "Pux\Exception" not found`).
- `compile(string $outFile): void` throws `CacheWriteFailed` (L36; before: `int|false`).
- What to do: use the return value of `load()` as a `Mux`; catch `InvalidCallback` around
  `merge()` with validation.

## Exceptions instead of warnings and null

Every exception thrown by Pux for a routing or execution error implements
`Pux\Exception\PuxException`. Code that checked for a warning, `null`, `false` or `0` now gets:

| Situation | 2.0.x-dev | 3.0 |
|---|---|---|
| unbalanced parenthesis or invalid `require` in a pattern | broken expression, warnings at dispatch | `PatternSyntax` from `add()` |
| invalid expression in a `RouteRequest` predicate | warning, "no match" | `PatternSyntax` |
| PCRE failure while dispatching (cache file, backtrack limit) | route skipped | `PatternSyntax` |
| callback with a missing class or method | `ReflectionException` or `\LogicException` | `InvalidCallback` |
| required parameter without a value | `\Exception` or `ArgumentCountError` | `MissingArgument` |
| cache file cannot be written | `false` and a warning | `CacheWriteFailed` |
| unknown request method name | `0` | `\ValueError` |
| request without `REQUEST_METHOD` or path | defaults or warnings | `\InvalidArgumentException` |
| `MuxCompiler::load()` of a file without a `Mux` | `true` | `\UnexpectedValueException` |
| mount point without its submux, non-string `domain` or `pattern` | warning | `\LogicException` |
| route option `id` that is not an `int` or `string` | warning | `\InvalidArgumentException` |
| route options `vars`, `default` or `constructor_args` that are not arrays | warning or `TypeError` | `\InvalidArgumentException` |

A path that matches no route still returns `null` from `dispatch()`.
