# Glossary

Terms used in test names, ADRs and code reviews. Each definition describes what the code in
`src/` does at the time of writing (Stage 0 of the PHP 8.2 port), with the class and method that
implement it. Where the code contradicts its own docblock, both are named. Defect IDs (R*, L*,
P*) refer to the defect registry kept with the port plan.

## Mux

An instance of `Pux\Mux`: an ordered list of routes plus the data needed to dispatch them.

- `Mux::$routes` - list of route arrays, checked in insertion order by `Mux::match()`
  (`Mux::sort()` reorders it with `MuxCompiler::sort_routes`).
- `Mux::$routesById` - routes registered with the `id` option, keyed by that id.
- `Mux::$submux` - submuxes attached without expansion, keyed by `Mux::getId()`.
- `Mux::$expand` - whether `mount()` copies submux routes into this mux (default `true`).
- `Mux::$id` - integer id assigned lazily from the static counter `Mux::$id_counter`.

Routes are registered with `add()` (any method) and the method helpers `get()`, `post()`,
`put()`, `delete()`, `patch()`, `head()`, `options()`, `any()`.

## Route

A four-element array, the unit stored in `Mux::$routes` (docblock of `Mux::$routes`):

```
[0 => bool $pcre, 1 => string $patternOrRegex, 2 => mixed $callback, 3 => array $options]
```

`Mux::add()` decides the kind of route: a pattern containing `:` becomes a PCRE route,
anything else a static route. A callback string of the form `Class:method` is split into
`['Class', 'method']`.

## Static route

A route with element `0 === false`. Element `1` is the literal path. `Mux::match()` accepts it
when `$route[1] == $path`; when the callback is a submux reference (integer id, `Mux` instance or
`Pux\Handler` instance) it accepts a prefix match instead (`strncmp` on the length of
`$route[1]`).

## PCRE route

A route with element `0 === true`. Element `1` is the compiled regular expression produced by
`PatternCompiler::compile()` (`#^...$#xs`) or, for a submux mount point,
`PatternCompiler::compilePrefix()` (`#^...#xs`, no end anchor). Element `3` holds the compiler
output merged with the route options: `regex`, `compiled`, `pattern` (the source pattern),
`tokens`, `variables`. `Mux::match()` accepts it when `preg_match($route[1], $path)` succeeds and
stores the matches in `$route[3]['vars']`.

Pattern syntax handled by `PatternCompiler` (a scanner that tracks parenthesis depth, Stage 4a
L8, L9): `:name` is a variable, text in parentheses is an optional group (groups may follow one
another and nest), text between them is literal. Example from the class docblock:
`/blog/item/:id(.:format)`. The character before the colon is the variable separator; a
variable at the start of the pattern or of a group has none (`:id`, `(:format)`). A variable's
default regex excludes the separator characters around it (`/` when there are none); the
`require` option overrides it. An unbalanced parenthesis, or a `require` value that makes the
expression invalid, throws `Pux\Exception\PatternSyntax`. White space in text and separators is
matched literally (Stage 4b L29; the `x` flag would otherwise drop it). A catch-all variable is
written with `require`: `/search/:filters` with `['require' => ['filters' => '.+']]` captures
`a/b/c` (upstream issue #109); `+` after a variable name is literal text. Paths are matched as
given, so the caller decodes them; the query string is not part of the path (issue #103, see
Route request).

## Mount

`Mux::mount($pattern, $target, $options)`: attach the routes of another mux under the path
prefix `$pattern`. `$target` is resolved to a `Mux` first:

- an `Expandable` object (a controller) - `$target->expand($options)`;
- a `Closure` - called with a new `Mux`, which it fills (or returns a `Mux` to use instead);
- another callable - called with a new `Mux`.

The signature accepts `Mux|Expandable|Closure|callable` (defect R1, closed in Stage 3).
`mount()` writes `mount_path` into the options it propagates; route options hold no objects
(Stage 4a L10). What happens next depends on `Mux::$expand`, see Expand.

## Submux

A `Mux` attached to a parent through `mount()`; the submux holds no link to its parent.
With `expand = false` the parent stores it in `Mux::$submux[$id]` and adds a route whose
callback is the integer id; `Mux::dispatch()` then strips the matched prefix from the path and
calls `$submux->dispatch()` with the remainder and the same `RouteRequest`. The named variables
matched by a PCRE mount point are added to the `vars` of the submux route; on a name clash the
submux value wins (Stage 4a L13).

## Expand (flattening)

Two related operations, both called expand in the code:

1. **Mux expansion**: with `Mux::$expand === true` (the default), `mount()` copies every submux route into the
   parent, prefixing its pattern with the mount pattern. The copy is a PCRE route when either
   the submux route is a PCRE route or the mount pattern contains `:`; its options are
   `array_replace_recursive($mountOptions, $route[3])`. After expansion the parent dispatches
   without recursion into the submux.
2. **Controller expansion**: `Expandable::expand(array $options = [], $dynamic)` turns a
   controller into a `Mux`. `ExpandableController::expand()` adds one route per entry of
   `ControllerRouteBuilder::build($this)` and calls `Mux::sort()`; the callback is
   `[static::class, $action]`, or `[$this, $action]` when `$dynamic` is true. The interface and
   both implementations default to `$dynamic = false` (Stage 4b L19).

## Action

A public controller method whose name ends with `Action`, excluding `runAction` and `hasAction`
(`ControllerRouteBuilder::parseActionMethods()`). `ControllerRouteBuilder::build()` maps each
action to a route `[path, actionName, options]`:

- path from the `@Route("...")` docblock annotation when present;
- otherwise `indexAction` maps to `''`;
- otherwise `translatePath()`: drop the `Action` suffix, replace each upper-case letter with `/`
  plus its lower-case form, prefix `/` (`pageUpdateAction` -> `/page/update`);
- the `@Method("GET")` annotation sets `options['method']` through
  `Mux::convertRequestMethodConstant()`.

Annotations declared on a parent class method apply to an overriding child method that has
none (`tests/ControllerRouteBuilder/BuildTest.php`,
`test_build_child_override_without_annotations_keeps_parent_annotations`, fixture
`ChildController`).

`RouteExecutor::execute($route)` runs a route callback: a Closure, a function name,
`[class, method]` (the class is instantiated, with `options['constructor_args']` if present),
`[object, method]`, or an invokable object (`Mux::add()` turns `'Class:method'` into
`[class, method]`); a missing class or method, or a callback that is not callable, throws
`Pux\Exception\InvalidCallback`.

**Argument binding** (`ArgumentResolver`, used by `RouteExecutor::execute()`,
`Controller::call()` and `Controller::runAction()`), per parameter:

1. the route variable of the same name (`options['vars']`);
2. else, for the parameters left unbound by step 1 in parameter order, the next named route
   variable that step 1 did not take (string keys of `vars` in their order; numeric keys are
   never bound);
3. else the route default of that name (`options['default']`, falsy values included);
4. else the parameter's own default value (the argument is left out; a variadic parameter takes
   nothing);
5. else `Pux\Exception\MissingArgument` naming the parameter and the callback; a nullable
   parameter without a default value is required too.

Arguments are passed as named arguments, so a left-out optional parameter does not shift the
others (Stage 4b L5, L6, L7; upstream issue #112).

## Resource

A RESTful controller registered under a resource id. `RESTfulMuxBuilder::addResource($id,
Expandable $controller)` stores the controller; `RESTfulMuxBuilder::build()` mounts the expansion
of every stored resource once at `$prefix . '/' . $id` and returns the Mux (Stage 4b L14). `RESTfulController::expand()` produces five routes:

| Pattern | Method | Action |
|---|---|---|
| `/:id` | POST | `updateAction` |
| `/:id` | GET | `loadAction` |
| `/:id` | DELETE | `deleteAction` |
| `''` | POST | `createAction` |
| `''` | GET | `collectionAction` |

## Route options

Keys of element `3` of a route that the code reads. The `Mux::$routes` docblock lists `method`,
`domain`, `secure`, `id`, `requirements`, `mount_path`; the code reads `require`, not
`requirements`.

| Key | Set by | Read by | Meaning |
|---|---|---|---|
| `method` | `get()`, `post()` and the other helpers, `@Method` | `Mux::match()` | request method constraint, stored as the integer value of a `Pux\RequestMethod` case; `add()` accepts the case, its name in any case or its value, anything else throws `\ValueError`. The deprecated `REQUEST_METHOD_*` constants (`src/constants.php`) hold the same values |
| `domain` | caller | `Mux::match()` | host constraint, compared with `RouteRequest::hostEqual()` (case-insensitive; no `HTTP_HOST` means no match) |
| `secure` | caller | `Mux::match()` | `true` requires HTTPS: the `HTTPS` key of the request server array non-empty and not `off`; `false` or absent is no constraint |
| `require` | caller | `PatternCompiler::compilePattern()` | map variable name -> regex that replaces the default variable regex |
| `default` | caller | `PatternCompiler`, `RouteExecutor` | map variable name -> default value; trailing variables with a default become optional in the compiled regex; `RouteExecutor` and `Controller::call()` use the value in step 3 of argument binding (falsy values included) |
| `id` | caller | `Mux::add()`, `Mux::getRoute()`, `Mux::url()` | route name, key in `Mux::$routesById` |
| `mount_path` | `Mux::mount()` | none in `src/` | the mount pattern the route was attached under |

Keys written during processing: `vars` (regex matches, set by `Mux::match()`, plus the named
variables of a PCRE mount point), `constructor_args` (read by `RouteExecutor::execute()`), and
the `PatternCompiler` output keys listed under PCRE route. `Mux::mount()` no longer writes `mux`
(the submux object, removed in Stage 4a L10).

## Mount point

The route that `mount()` adds to the parent when `Mux::$expand === false`: its pattern is the
mount pattern, its callback the integer submux id. A static mount point matches by prefix; a
PCRE mount point is compiled with `PatternCompiler::compilePrefix()` (no end anchor). In test
names: "mount point".

## Named route

A route registered with the `id` option. `Mux::getRoute($id)` returns it and `Mux::url($id,
$params)` builds a path: a static route returns its path; for a PCRE route each `:name` of the
source pattern is replaced by `$params['name']` as given (the caller encodes values), an
optional group whose variables are not given is removed. An unknown id throws
`\RuntimeException`.

## Handler

An object implementing `Pux\Handler`: `call(array &$environment, array $response)`.
`Pux\Controller\Controller` is one. A static route whose callback is a handler is matched by
path prefix (see Static route). The interface replaces `App` of the former third-party package
phpsgi/phpsgi with the same contract.

## Route request

A `Pux\RouteRequest`: request method, path, server parameters, headers and the parameter arrays
(query, body, cookies, session) that the predicates (`pathMatch`, `hostEqual`,
`matchConstraints` and others) test. Built by `RouteRequest::fromServer` from an array of the
shape of `$_SERVER` (the superglobal itself only when no array is given), by `createFromEnv`
from an environment array, or by `create($method, $path, $env)`. Both `fromServer` and
`createFromEnv` take the path from `PATH_INFO`, else from `REQUEST_URI` without its query string
(Stage 4b L32); the query string is matched through `queryStringMatch()` on `QUERY_STRING`.
`hostMatch()` and `queryStringMatch()` compile the expression even when their server key is
absent (then they return `false`), so an invalid expression always throws `PatternSyntax`
(Stage 4b L30).

`Mux::dispatch($path, $request)` takes the method, host and HTTPS of every constraint from
`$request` and passes it on to submuxes. Without `$request`, it calls `RouteRequest::fromServer()`
only when a route with a `method`, `domain` or `secure` constraint matches the path; routes
without constraints match without any request, and `fromServer()` throws
`\InvalidArgumentException` when the server array has no `REQUEST_METHOD` (the CLI).

## Route cache

PHP code that rebuilds a `Mux`: `Mux::export()` returns code that calls `\Pux\Mux::__set_state()`
with the keys `id`, `routes`, `routesById` and `submux` (each submux exported the same way, the
parent link not exported); `Mux::compile($file)` and `MuxCompiler::compile($file)` write
`<?php return <export>;` to a file. `MuxCompiler::load($file)` requires such a file, merges
its routes and returns the merged Mux (Stage 4b L17). With `MuxCompiler::$callbackValidation`,
`merge()` checks `[class, method]` callbacks with `class_exists` and `method_exists` on the class
name, without instantiating it (Stage 4b L18). `Mux::__set_state()` requires `routes`; `id`,
`routesById` and `submux` are optional (Stage 4b L21). In test names: "round trip" is export followed by `eval`.

## Test groups

Red tests are kept in the PHPUnit group `known-defect`, which `phpunit.xml.dist` excludes, so
that `composer test` stays green. Such a test specifies behavior that fails today because of a
defect in the registry; the docblock names the ID (`@see PLAN-port-php82.md L4`). The cycle that
fixes the defect removes the group. (Until Stage 1 a second group, `pending-stage-1`, held the
tests that could not load before the third-party parent classes were replaced.)

`composer test:red` runs only this group, `composer test:all` runs every test.

## Seam

A public interface on which tests are written: tests call it with real objects and assert on
its observable result, never on internals. The eight seams of this project, and the methods that
are explicitly not seams, are listed in
[ADR 0002](docs/adr/0002-tdd-at-seams.md).
