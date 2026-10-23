# PuxDZ

<!--
The ```php blocks of this file are copies of files in eg/. bin/check-readme-examples compares
each block with the file named in the marker line above it, and bin/run-examples runs every
eg/*.php and compares its output with eg/<name>.out; both run in CI. To change an example,
edit the file in eg/, update eg/<name>.out, and copy the file into this README.
-->

PuxDZ is an HTTP request router for PHP 8.2 and later without runtime dependencies: the package
requires `php: ^8.2` and `ext-pcre` and nothing else. It maps a request path, a request method,
a host and the HTTPS flag to a callback, and binds the variables of the path to the parameters
of the callback.

PuxDZ 3.0 is a fork of [Pux](https://github.com/c9s/Pux) by Yo-An Lin, ported from the 2.0.x-dev
branch to PHP 8.2-8.5. The C extension, the third-party packages and the PHP 5 tooling of Pux are
removed; the defects found in 2.0.x-dev are fixed with a test for each. The changes are listed
in [CHANGES.md](CHANGES.md), the incompatible ones with migration steps in
[UPGRADE-3.0.md](UPGRADE-3.0.md).

- [Requirements and installation](#requirements-and-installation)
- [Quick start](#quick-start)
- [Pattern syntax](#pattern-syntax)
- [Request constraints](#request-constraints)
- [Mounting](#mounting)
- [Controllers](#controllers)
- [Named routes and url()](#named-routes-and-url)
- [Route cache](#route-cache)
- [Argument binding](#argument-binding)
- [Error handling](#error-handling)
- [Testing your routes](#testing-your-routes)
- [Development](#development)
- [Upgrading from 2.x](#upgrading-from-2x)
- [Credits and license](#credits-and-license)

## Requirements and installation

- PHP 8.2, 8.3, 8.4 or 8.5 (the CI matrix);
- `ext-pcre`.

```sh
composer require dzahdev/puxdz
```

The classes are in the namespace `Pux`, autoloaded through PSR-4 from `src/`.

## Quick start

<!-- example: eg/quick_start.php -->
```php
<?php

declare(strict_types=1);

/*
 * Quick start: routes for GET, POST and any method, dispatched for the current request and
 * executed. In a web server RouteRequest::fromServer() reads $_SERVER; on the command line the
 * example passes a server array built from the arguments.
 *
 *     php eg/quick_start.php                    GET /hello/world
 *     php eg/quick_start.php POST /orders       POST /orders
 */

namespace Pux\Examples;

use Pux\Mux;
use Pux\RouteExecutor;
use Pux\RouteRequest;

require __DIR__ . '/../vendor/autoload.php';

final class OrderController
{
    public function createAction(): string
    {
        return 'order created';
    }
}

$mux = new Mux();
$mux->get('/hello/:name', static fn (string $name): string => "hello $name");
$mux->post('/orders', [OrderController::class, 'createAction']);
$mux->any('/health', static fn (): string => 'ok');

$request = PHP_SAPI === 'cli'
    ? RouteRequest::fromServer([
        'REQUEST_METHOD' => $argv[1] ?? 'GET',
        'REQUEST_URI' => $argv[2] ?? '/hello/world',
    ])
    : RouteRequest::fromServer();

$route = $mux->dispatchRequest($request);
echo $route === null ? '404 Not Found' : RouteExecutor::execute($route), "\n";
```

```text
$ php eg/quick_start.php
hello world
$ php eg/quick_start.php POST /orders
order created
$ php eg/quick_start.php GET /orders
404 Not Found
```

`Pux\Mux` holds the routes. `get()`, `post()`, `put()`, `delete()`, `patch()`, `head()` and
`options()` add a route with the request method constraint of their name; `any()` and `add()`
add a route without one (`add()` takes the constraint in the option `method`). Each takes a
pattern, a callback and an array of route options.

`dispatch(string $path, ?RouteRequest $request = null)` and `dispatchRequest(RouteRequest
$request)` return the first matching route, or `null` when no route matches. A route is the
array `[bool $pcre, string $pattern, mixed $callback, array $options]`; for a pattern with
variables, `$options['vars']` holds the captures of `preg_match()` (named and numbered).

`Pux\RouteExecutor::execute(array $route)` calls the callback with the bound arguments and
returns its result. The callback is one of:

- a `Closure` or the name of a function;
- `[ClassName::class, 'method']`: the class is instantiated for each call, with the route
  option `constructor_args` (a list) as constructor arguments when it is given;
- `[$object, 'method']` or an invokable object;
- the string `'ClassName::method'` or `'ClassName:method'`, stored by `add()` as
  `[ClassName, method]`.

Routes are checked in the order in which they were added. `Mux::sort()` reorders them: routes
with variables first, each group by descending length of the expression or path.

## Pattern syntax

<!-- example: eg/patterns.php -->
```php
<?php

declare(strict_types=1);

/*
 * Pattern syntax: ":name" variables, optional groups in parentheses, 'require' expressions,
 * 'default' values, a catch-all variable and literal white space. Each line prints the named
 * variables that dispatch() captured, or "no route".
 *
 *     php eg/patterns.php
 */

namespace Pux\Examples;

use Pux\Mux;

require __DIR__ . '/../vendor/autoload.php';

$mux = new Mux();
// ":name" matches up to the next separator; a group in parentheses is optional.
$mux->any('/blog/:year/:month(/:slug)', 'blog');
// 'require' replaces the default expression of a variable.
$mux->any('/product/:id(.:format)', 'product', [
    'require' => ['id' => '\d+', 'format' => 'json|html'],
    'default' => ['format' => 'html'],
]);
// A catch-all variable is a 'require' expression that accepts "/".
$mux->any('/files/:path', 'files', ['require' => ['path' => '.+']]);
// White space in a pattern is matched literally.
$mux->any('/my documents/:name', 'documents');

$paths = ['/blog/2026/10', '/blog/2026/10/release', '/product/42.json', '/product/42',
    '/product/abc', '/files/docs/a/b.txt', '/my documents/cv', '/mydocuments/cv'];

foreach ($paths as $path) {
    $route = $mux->dispatch($path);
    if ($route === null) {
        echo "$path => no route\n";
        continue;
    }
    $vars = array_filter($route[3]['vars'], is_string(...), ARRAY_FILTER_USE_KEY);
    $json = json_encode($vars, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    echo "$path => $route[2] $json\n";
}
```

```text
$ php eg/patterns.php
/blog/2026/10 => blog {"year":"2026","month":"10"}
/blog/2026/10/release => blog {"year":"2026","month":"10","slug":"release"}
/product/42.json => product {"id":"42","format":"json"}
/product/42 => product {"id":"42"}
/product/abc => no route
/files/docs/a/b.txt => files {"path":"docs/a/b.txt"}
/my documents/cv => documents {"name":"cv"}
/mydocuments/cv => no route
```

A pattern without `:` is a static route: it matches a path that is equal to it (`===`). A
pattern with `:` is compiled by `Pux\PatternCompiler::compile()` into a regular expression
anchored at both ends:

- `:name` is a variable; the name is one or more of `[A-Za-z0-9_]`. The character before the
  colon (`/` in `/:id`, `-` in `/:year-:month`) is a separator, matched literally in front of
  the variable. By default a variable matches one or more characters other than the separators
  next to it, so `/:id` stops at the next `/`.
- Text in parentheses is an optional group: `/product/:id(.:format)`. Groups may follow one
  another and nest. An unclosed `(` or a `)` without its `(` throws
  `Pux\Exception\PatternSyntax` when the route is added.
- The option `require` maps a variable name to the expression it must match, in place of the
  default expression. An expression that makes the compiled pattern invalid throws
  `PatternSyntax` when the route is added.
- The option `default` maps a variable name to the value bound to the callback parameter when
  the variable is absent from the path (see [Argument binding](#argument-binding)). It is not
  added to `vars`.
- A catch-all variable is a `require` expression that accepts `/`: `/files/:path` with
  `['require' => ['path' => '.+']]`. A `+` after a variable name is literal text.
- White space in the pattern is matched literally: `/my documents/:name` does not match
  `/mydocuments/cv`.
- Any other character is literal text, `:` included when no name character follows it.

The variables of an optional group that is not in the path are left out of `vars`, also when a
required variable follows the group: `/a(/:b)/:c` with `['default' => ['b' => 'x']]` dispatches
`/a/1` with `vars` holding `c` only, and the callback gets `b = 'x'`, `c = '1'`.

## Request constraints

<!-- example: eg/constraints.php -->
```php
<?php

declare(strict_types=1);

/*
 * Request constraints: the options 'method', 'domain' and 'secure' are checked against the
 * RouteRequest given to dispatch(). Without a request, dispatch() builds one with
 * RouteRequest::fromServer() only when a route with a constraint matches the path.
 *
 *     php eg/constraints.php
 */

namespace Pux\Examples;

use Pux\Mux;
use Pux\RequestMethod;
use Pux\RouteRequest;

require __DIR__ . '/../vendor/autoload.php';

$mux = new Mux();
$mux->add('/items', 'replaceItems', ['method' => RequestMethod::PUT]);
$mux->add('/items', 'listItems', ['method' => 'get']);
$mux->any('/admin', 'admin', ['domain' => 'admin.example.com', 'secure' => true]);
$mux->any('/about', 'about');

$requests = [
    'PUT /items' => RouteRequest::create('PUT', '/items'),
    'GET /items' => RouteRequest::create('GET', '/items'),
    'POST /items' => RouteRequest::create('POST', '/items'),
    'GET https://admin.example.com/admin' => RouteRequest::create('GET', '/admin', [
        'HTTP_HOST' => 'admin.example.com',
        'HTTPS' => 'on',
    ]),
    'GET http://admin.example.com/admin' => RouteRequest::create('GET', '/admin', [
        'HTTP_HOST' => 'admin.example.com',
    ]),
    'GET https://www.example.com/admin' => RouteRequest::create('GET', '/admin', [
        'HTTP_HOST' => 'www.example.com',
        'HTTPS' => 'on',
    ]),
];

foreach ($requests as $label => $request) {
    echo $label, ' => ', $mux->dispatchRequest($request)[2] ?? 'no route', "\n";
}

// A route without constraints needs no request, so this works on the command line too.
echo '/about without a request => ', $mux->dispatch('/about')[2] ?? 'no route', "\n";
```

```text
$ php eg/constraints.php
PUT /items => replaceItems
GET /items => listItems
POST /items => no route
GET https://admin.example.com/admin => admin
GET http://admin.example.com/admin => no route
GET https://www.example.com/admin => no route
/about without a request => about
```

Three route options are checked against the request:

| Option | Value | The route matches when |
|---|---|---|
| `method` | a `Pux\RequestMethod` case, its name in any case (`'post'`) or its integer value | the request method equals it; an unknown name or value throws `\ValueError` when the route is added |
| `domain` | a host name | `HTTP_HOST` of the server array equals it, ignoring case; a request without `HTTP_HOST` does not match |
| `secure` | `true` | `HTTPS` of the server array is present, not empty and not `off`; `false` or an absent option is no constraint |

`Pux\RequestMethod` is an `int`-backed enum with the cases `GET`, `POST`, `PUT`, `DELETE`,
`PATCH`, `HEAD`, `OPTIONS`; `RequestMethod::fromName('post')` and `tryFromName()` read a method
name. The global constants `REQUEST_METHOD_GET` to `REQUEST_METHOD_OPTIONS` of 2.x are kept
with the same values and are deprecated until 4.0.

The request is a `Pux\RouteRequest`, a `final` class with `readonly` properties:

- `RouteRequest::fromServer(?array $server = null)` reads `$server`, or `$_SERVER` when it is
  `null`. It requires `REQUEST_METHOD` and `PATH_INFO` or `REQUEST_URI` (the path of
  `REQUEST_URI` without the query string) and throws `\InvalidArgumentException` otherwise.
- `RouteRequest::create(string $method, string $path, array $server = [])` builds a request
  from its parts, for tests and command-line scripts.
- `RouteRequest::createFromEnv(array $env)` reads a flat server array or an array with the keys
  `_SERVER`, `_REQUEST`, `_GET`, `_POST`, `_COOKIE`, `_SESSION`; the method defaults to `GET`.
- `new RouteRequest($method, $path, $server, $parameters, $query, $body, $cookies, $session)`.

**Dispatch without a request.** `dispatch($path)` without a `RouteRequest` matches routes that
have no constraint without reading any request. When the first route whose pattern matches the
path has a `method`, `domain` or `secure` option, `dispatch()` builds the request with
`RouteRequest::fromServer()` and uses it for that route and the following ones. On the command
line `$_SERVER` has no `REQUEST_METHOD`, so this throws `\InvalidArgumentException`; pass a
`RouteRequest` there.

`RouteRequest` also has predicates for application code: `pathEqual`, `pathMatch`, `pathLike`,
`pathContain`, `pathStartWith`, `pathEndWith`, `hostEqual`, `hostMatch`, `isOneOfHosts`,
`portEqual`, `queryStringMatch`, `requestMethodEqual` and `matchConstraints`. The predicates that
take a regular expression throw `PatternSyntax` for an invalid one.

## Mounting

<!-- example: eg/mounting.php -->
```php
<?php

declare(strict_types=1);

/*
 * Mounting: mount() attaches a Mux, an Expandable controller or a builder closure under a
 * path. With $expand = true (the default) mount() copies the routes into the parent with the
 * mount path as prefix; with $expand = false the parent keeps one route per mount point, and
 * dispatch() dispatches the rest of the path in the submux.
 *
 *     php eg/mounting.php
 */

namespace Pux\Examples;

use Pux\Controller\ExpandableController;
use Pux\Mux;
use Pux\RouteExecutor;

require __DIR__ . '/../vendor/autoload.php';

final class PageController extends ExpandableController
{
    public function indexAction(): string
    {
        return 'page index';
    }

    public function contactAction(): string
    {
        return 'contact page';
    }
}

function buildMux(bool $expand): Mux
{
    $users = new Mux();
    $users->any('/profile', static fn (string $user): string => "profile of $user");
    $users->any(
        '/posts/:post',
        static fn (string $user, string $post): string => "post $post of $user",
    );

    $mux = new Mux();
    $mux->expand = $expand;
    // A Mux.
    $mux->mount('/users/:user', $users);
    // An Expandable controller: its *Action methods become routes.
    $mux->mount('/pages', new PageController());
    // A closure that fills the Mux it receives (it may instead return a Mux).
    $mux->mount('/api', static function (Mux $api): void {
        $api->any('/status', static fn (): string => 'api ok');
    });

    return $mux;
}

$paths = ['/users/bob/profile', '/users/bob/posts/7', '/pages', '/pages/contact', '/api/status'];

foreach ([true, false] as $expand) {
    $mux = buildMux($expand);
    printf("expand = %s: %d routes in the parent Mux\n", var_export($expand, true), $mux->length());
    foreach ($paths as $path) {
        $route = $mux->dispatch($path);
        echo "  $path => ", $route === null ? 'no route' : RouteExecutor::execute($route), "\n";
    }
}
```

```text
$ php eg/mounting.php
expand = true: 5 routes in the parent Mux
  /users/bob/profile => profile of bob
  /users/bob/posts/7 => post 7 of bob
  /pages => page index
  /pages/contact => contact page
  /api/status => api ok
expand = false: 3 routes in the parent Mux
  /users/bob/profile => profile of bob
  /users/bob/posts/7 => post 7 of bob
  /pages => page index
  /pages/contact => contact page
  /api/status => api ok
```

`mount(string $pattern, Mux|Expandable|Closure|callable $target, array $options = [])` attaches
routes under a mount pattern. The target is:

- a `Mux`;
- an `Expandable` object, for example an `ExpandableController`: `mount()` calls
  `$target->expand($options)` to get its `Mux`;
- a `Closure` that receives a new `Mux` to fill; it may instead return the `Mux` to mount
  (another return value that is not `null` throws `\LogicException`);
- any other callable, which receives a new `Mux` to fill.

`mount()` sets the option `mount_path` to the mount pattern and passes its options to
`expand()` of an `Expandable` target. What it does next depends on the public property
`$expand` of the `Mux` it is called on, read at the time of the call:

| | `$expand = true` (default) | `$expand = false` |
|---|---|---|
| Routes in the parent | one copy of every submux route, with the mount pattern as prefix and the options of `mount()` merged into its options | one route per mount point with the options of `mount()`; its callback is the submux id, the submux is kept in `$submux` (`getSubMux($id)`) |
| Matching | the full path against each copied route | a static mount pattern matches as a path prefix, a mount pattern with variables is compiled as a prefix expression; `dispatch()` then dispatches the rest of the path in the submux |
| Variables of the mount pattern | part of the copied pattern | added to `vars` of the submux route; on a name clash the submux value is kept |
| Route cache | routes only | routes plus the submux, exported recursively |

## Controllers

<!-- example: eg/expand.php -->
```php
<?php

declare(strict_types=1);

/*
 * Controller expansion: mount() turns the public *Action methods of an ExpandableController
 * into routes under the mount path. The path comes from the #[Route] attribute, else from the
 * method name: indexAction is the mount path itself, helloAction is /hello, pageUpdateAction is
 * /page/update.
 *
 *     php eg/expand.php
 */

namespace Pux\Examples;

use Pux\Attribute\Route;
use Pux\Controller\ExpandableController;
use Pux\Mux;
use Pux\RequestMethod;
use Pux\RouteExecutor;
use Pux\RouteRequest;

require __DIR__ . '/../vendor/autoload.php';

final class MyController extends ExpandableController
{
    public function indexAction(): string
    {
        return 'index';
    }

    public function helloAction(): string
    {
        return 'hello';
    }

    public function pageUpdateAction(): string
    {
        return 'page update';
    }

    #[Route('/items/:id', method: RequestMethod::POST)]
    public function saveAction(int $id): string
    {
        return "item $id saved";
    }
}

$mux = new Mux();
$mux->mount('/my', new MyController());

$requests = [
    RouteRequest::create('GET', '/my'),
    RouteRequest::create('GET', '/my/hello'),
    RouteRequest::create('GET', '/my/page/update'),
    RouteRequest::create('POST', '/my/items/5'),
    RouteRequest::create('GET', '/my/items/5'),
];

foreach ($requests as $request) {
    $route = $mux->dispatchRequest($request);
    printf(
        "%s %s => %s\n",
        $request->getRequestMethod(),
        $request->getPath(),
        $route === null ? 'no route' : RouteExecutor::execute($route),
    );
}
```

```text
$ php eg/expand.php
GET /my => index
GET /my/hello => hello
GET /my/page/update => page update
POST /my/items/5 => item 5 saved
GET /my/items/5 => no route
```

`Pux\Controller\ExpandableController::expand()` builds a `Mux` with one route per public
method whose name ends in `Action` (`runAction` and `hasAction` excluded), through
`Pux\Builder\ControllerRouteBuilder::build()`:

- The attribute `#[Pux\Attribute\Route(string $path, ?RequestMethod $method = null)]` sets the
  path and, when `method` is given, the request method constraint.
- Without the attribute, the path is derived from the method name: `indexAction` is the mount
  path itself (`''`), `helloAction` is `/hello`, `pageUpdateAction` is `/page/update`.
- An override without an attribute and without annotations keeps the route of the parent
  method.
- The docblock annotations of 2.x, a line `@Route("/path")` and a line `@Method("POST")` in the
  docblock of the action, are still read for a method without the attribute. They are
  deprecated since 3.0 and removed in 4.0. When a method has the attribute, its docblock is not
  read.

The route callbacks are `[ClassName, action]`, so `RouteExecutor` creates a new controller for
each call and the routes can be exported to a route cache. `expand($options, dynamic: true)`
stores `[$this, action]` instead.

`Pux\Controller\Controller`, the base class, also implements `Pux\Handler`:
`call(array &$environment, array $response)` runs the action named by the environment key
`pux.controller_action` with the route in `pux.route`; `runAction($action, $vars)` and
`forward($controller, $action, $parameters)` run an action by its name without the suffix
`Action`, with the same argument binding.

<!-- example: eg/restful.php -->
```php
<?php

declare(strict_types=1);

/*
 * RESTful resources: RESTfulMuxBuilder mounts each RESTfulController at
 * "<prefix>/<resource id>"; the controller routes its five actions by request method.
 *
 *     php eg/restful.php
 */

namespace Pux\Examples;

use Pux\Controller\RESTfulController;
use Pux\MuxBuilder\RESTfulMuxBuilder;
use Pux\RouteExecutor;
use Pux\RouteRequest;

require __DIR__ . '/../vendor/autoload.php';

final class ProductResource extends RESTfulController
{
    public function collectionAction(): string
    {
        return 'list products';
    }

    public function createAction(): string
    {
        return 'create a product';
    }

    public function loadAction(mixed $id): string
    {
        return "load product $id";
    }

    public function updateAction(mixed $id): string
    {
        return "update product $id";
    }

    public function deleteAction(mixed $id): string
    {
        return "delete product $id";
    }
}

$builder = new RESTfulMuxBuilder(null, ['prefix' => '/api']);
$builder->addResource('products', new ProductResource());
$mux = $builder->build();

$requests = [
    RouteRequest::create('GET', '/api/products'),
    RouteRequest::create('POST', '/api/products'),
    RouteRequest::create('GET', '/api/products/3'),
    RouteRequest::create('POST', '/api/products/3'),
    RouteRequest::create('DELETE', '/api/products/3'),
];

foreach ($requests as $request) {
    $route = $mux->dispatchRequest($request);
    printf(
        "%s %s => %s\n",
        $request->getRequestMethod(),
        $request->getPath(),
        $route === null ? 'no route' : RouteExecutor::execute($route),
    );
}
```

```text
$ php eg/restful.php
GET /api/products => list products
POST /api/products => create a product
GET /api/products/3 => load product 3
POST /api/products/3 => update product 3
DELETE /api/products/3 => delete product 3
```

`Pux\MuxBuilder\RESTfulMuxBuilder` takes an optional `Mux` and the option `prefix`.
`addResource(string $id, Expandable $resource)` registers a resource and `build()` mounts every
registered resource at `rtrim(prefix, '/') . '/' . $id` and returns the `Mux`: the default
prefix `'/'` mounts `product` at `/product`, the prefixes `'/api'` and `'/api/'` at
`/api/product`. The resources are mounted by the first `build()` only: a second call returns the
same `Mux` unchanged, and `addResource()` after `build()` throws `\LogicException`.

`Pux\Controller\RESTfulController::expand()` routes five actions:

| Request | Action |
|---|---|
| `GET <path>` | `collectionAction()` |
| `POST <path>` | `createAction()` |
| `GET <path>/:id` | `loadAction($id)` |
| `POST <path>/:id` | `updateAction($id)` |
| `DELETE <path>/:id` | `deleteAction($id)` |

Its methods `code()`, `codeOk()`, `codeCreated()` and the other `code*()` methods call
`http_response_code()`; they are deprecated since 3.0 and removed in 4.0. Return the response
array `[code, headers, body]` from the action instead.

## Named routes and url()

<!-- example: eg/named_routes.php -->
```php
<?php

declare(strict_types=1);

/*
 * Named routes: the option 'id' registers a route under a name, and url() builds a path from
 * it. Optional groups whose variables are not given are dropped. Values are inserted as given,
 * so the caller encodes them.
 *
 *     php eg/named_routes.php
 */

namespace Pux\Examples;

use Pux\Mux;

require __DIR__ . '/../vendor/autoload.php';

$mux = new Mux();
$mux->any('/', 'home', ['id' => 'home']);
$mux->any('/blog/:year/:month(/:slug)', 'post', ['id' => 'blog.post']);

echo $mux->url('home'), "\n";
echo $mux->url('blog.post', ['year' => 2026, 'month' => 10]), "\n";
echo $mux->url('blog.post', ['year' => 2026, 'month' => 10, 'slug' => rawurlencode('a b')]), "\n";
```

```text
$ php eg/named_routes.php
/
/blog/2026/10
/blog/2026/10/a%20b
```

The route option `id` (an `int` or a `string`) registers a route by name; `getRoute($id)`
returns it or `null`. `url($id, array $params = [])` returns the path of a static route, and for
a route with variables replaces each `:name` of the pattern with `$params['name']` and drops the
optional groups whose variables are not given. The values are inserted as given: encode them,
for example with `rawurlencode()`. An unknown id throws `Pux\Exception\RouteNotFound`.

## Route cache

<!-- example: eg/route_cache.php -->
```php
<?php

declare(strict_types=1);

/*
 * Route cache: Mux::compile() writes PHP code that rebuilds the Mux through Mux::__set_state();
 * requiring the file returns the Mux without compiling the patterns again. The callbacks must
 * be exportable with var_export(): strings and [class, method] arrays, not closures.
 *
 *     php eg/route_cache.php
 */

namespace Pux\Examples;

use Pux\Mux;

require __DIR__ . '/../vendor/autoload.php';

$cacheFile = sys_get_temp_dir() . '/pux-example-routes-' . getmypid() . '.php';

$mux = new Mux();
$mux->any('/product/:id', ['ProductController', 'itemAction'], ['require' => ['id' => '\d+']]);
$mux->any('/product', ['ProductController', 'listAction']);

try {
    // Throws Pux\Exception\CacheWriteFailed when the file cannot be written.
    $mux->compile($cacheFile);

    // Later, for example in the next request:
    $cached = require $cacheFile;
    if (!$cached instanceof Mux) {
        throw new \UnexpectedValueException("The route cache $cacheFile returns no Mux.");
    }

    $route = $cached->dispatch('/product/42');
    echo implode('::', $route[2] ?? ['no route']), ' id=', $route[3]['vars']['id'] ?? '-', "\n";
} finally {
    unlink($cacheFile);
}
```

```text
$ php eg/route_cache.php
ProductController::itemAction id=42
```

- `Mux::export()` returns a PHP expression that calls `\Pux\Mux::__set_state()` with an array
  of the keys `id`, `routes`, `routesById` and `submux`; each submux is exported the same way.
  The callbacks are written with `var_export()`, so a closure or an object callback cannot be
  cached.
- `Mux::__set_state(array $state)` requires `routes`; `id`, `routesById` and `submux` are
  optional and other keys are ignored, so cache files written by 2.x load.
- `Mux::compile(string $file, bool $sort = true): void` sorts the routes (by default) and writes
  `<?php return <export>;` to `$file`. A file that cannot be written throws
  `Pux\Exception\CacheWriteFailed`.
- A cache file is PHP code: require only files that your application wrote. A route whose
  expression in the cache file is invalid throws `PatternSyntax` when `dispatch()` reaches it.

`Pux\MuxCompiler` merges several route definition files into one cache file:

<!-- example: eg/hello_routes.php -->
```php
<?php

declare(strict_types=1);

/*
 * A route definition file: it returns a Mux. eg/hello_compile.php compiles it into a route
 * cache file with MuxCompiler.
 */

namespace Pux\Examples;

use Pux\Mux;

require_once __DIR__ . '/../vendor/autoload.php';

$mux = new Mux();
$mux->add('/hello/:name', 'Pux\Examples\GreetingController::helloAction', [
    'require' => ['name' => '[a-z]+'],
]);

return $mux;
```

<!-- example: eg/hello_compile.php -->
```php
<?php

declare(strict_types=1);

/*
 * Route cache: MuxCompiler loads a route definition file, writes the merged routes to a PHP
 * file, and the cache file is required to get the Mux back without compiling patterns again.
 *
 *     php eg/hello_compile.php   prints "hello world"
 */

namespace Pux\Examples;

use Pux\Mux;
use Pux\MuxCompiler;
use Pux\RouteExecutor;

require __DIR__ . '/../vendor/autoload.php';

final class GreetingController
{
    public function helloAction(string $name): string
    {
        return "hello $name";
    }
}

$cacheFile = tempnam(sys_get_temp_dir(), 'pux-routes-');
if ($cacheFile === false) {
    throw new \RuntimeException('Cannot create a temporary file for the route cache.');
}

try {
    $compiler = new MuxCompiler();
    $compiler->callbackValidation = true;
    $compiler->load(__DIR__ . '/hello_routes.php');
    $compiler->compile($cacheFile);

    $mux = require $cacheFile;
    if (!$mux instanceof Mux) {
        throw new \UnexpectedValueException("The route cache $cacheFile returns no Mux.");
    }

    $route = $mux->dispatch('/hello/world');
    if ($route === null) {
        throw new \UnexpectedValueException('The cached Mux does not dispatch /hello/world.');
    }
    echo RouteExecutor::execute($route), "\n";
} finally {
    unlink($cacheFile);
}
```

```text
$ php eg/hello_compile.php
hello world
```

- `load(string $file): Mux` requires a file that returns a `Mux`, merges its routes and returns
  the merged `Mux`; a file that returns anything else throws `\UnexpectedValueException`.
- `merge(Mux $mux)` appends the routes of `$mux` and gives each mount point a new submux id.
- With `$callbackValidation = true`, `merge()` checks every `[class, method]` and
  `[object, method]` callback without creating the controller and throws
  `Pux\Exception\InvalidCallback` for a missing class or method.
- `compile(string $file): void` sorts the merged routes and writes the cache file, or throws
  `CacheWriteFailed`.

## Argument binding

<!-- example: eg/binding.php -->
```php
<?php

declare(strict_types=1);

/*
 * Argument binding: RouteExecutor::execute() binds route variables to the action parameters by
 * name, then by position, then from the route option 'default', then from the parameter
 * default. A value for an int, float, string or bool parameter is converted when nothing is
 * lost ("7" to 7, "off" to false); otherwise PHP throws a TypeError.
 *
 *     php eg/binding.php
 */

namespace Pux\Examples;

use Pux\Mux;
use Pux\RouteExecutor;

require __DIR__ . '/../vendor/autoload.php';

final class ReportController
{
    public function byNameAction(string $month, int $year): string
    {
        return "by name: year=$year month=$month";
    }

    public function byPositionAction(string $first, string $second): string
    {
        return "by position: first=$first second=$second";
    }

    public function draftsAction(int $page, string $format = 'html', bool $draft = true): string
    {
        $draft = var_export($draft, true);

        return "defaults: page=$page format=$format draft=$draft";
    }
}

$mux = new Mux();
// The parameter names match the variable names, in another order.
$mux->any('/by-name/:year/:month', [ReportController::class, 'byNameAction']);
// No parameter name matches: the variables are bound in pattern order.
$mux->any('/by-position/:a/:b', [ReportController::class, 'byPositionAction']);
// page comes from 'default' when the group is omitted ("0" is bound too); format keeps its
// own default; draft is converted from "off" to false.
$mux->any('/reports/:draft(/:page)', [ReportController::class, 'draftsAction'], [
    'default' => ['page' => '0'],
]);

foreach (['/by-name/2026/10', '/by-position/x/y', '/reports/off', '/reports/yes/3'] as $path) {
    $route = $mux->dispatch($path) ?? throw new \UnexpectedValueException("No route for $path.");
    echo RouteExecutor::execute($route), "\n";
}
```

```text
$ php eg/binding.php
by name: year=2026 month=10
by position: first=x second=y
defaults: page=0 format=html draft=false
defaults: page=3 format=html draft=true
```

`RouteExecutor::execute()`, `Controller::call()` and `Controller::runAction()` bind arguments by
one rule, applied to the parameters of the callback in order:

1. **By name**: a parameter takes the route variable of the same name.
2. **By position**: each parameter left unbound takes the next named route variable that step 1
   did not take, in pattern order. The numbered captures of `preg_match()` are never bound.
3. **Route default**: a parameter still unbound takes the value of the option `default` of its
   name; `'0'`, `''` and other falsy values are bound too.
4. **Parameter default**: an optional parameter still unbound is left out, so PHP uses its
   default value. A variadic parameter takes no route value.
5. Otherwise `Pux\Exception\MissingArgument` names the parameter and the callback. A nullable
   parameter without a default value is required.

The arguments are passed by name, and the callback is called in strict types mode. A value bound
in steps 1-3 to a parameter declared with one scalar type is converted when nothing is lost:

| Parameter type | Accepted value | Result |
|---|---|---|
| `int` | a string that passes `FILTER_VALIDATE_INT` (`"7"`, `"-3"`) | `int` |
| `float` | an `int` or a numeric string | `float` |
| `string` | an `int` or a `float` | `string` |
| `bool` | `"1"`, `"0"`, `"true"`, `"false"`, `"on"`, `"off"`, `"yes"`, `"no"`, `""` | `bool` |

Any other value (`"2.5"` or `"abc"` for `int`) is passed unchanged, and PHP throws a
`\TypeError`. Union types and class types are not converted.

## Error handling

<!-- example: eg/errors.php -->
```php
<?php

declare(strict_types=1);

/*
 * Errors: a routing or execution error throws an exception that implements
 * Pux\Exception\PuxException; invalid arguments throw the standard PHP exceptions. A path that
 * matches no route is not an error: dispatch() returns null.
 *
 *     php eg/errors.php
 */

namespace Pux\Examples;

use Pux\Exception\CacheWriteFailed;
use Pux\Exception\InvalidCallback;
use Pux\Exception\MissingArgument;
use Pux\Exception\PatternSyntax;
use Pux\Exception\PuxException;
use Pux\Exception\RouteNotFound;
use Pux\Mux;
use Pux\RouteExecutor;
use Pux\RouteRequest;

require __DIR__ . '/../vendor/autoload.php';

final class GreetController
{
    public function greetAction(string $name): string
    {
        return "hello $name";
    }
}

/**
 * Runs $action and prints the short name of the exception it throws.
 *
 * @param class-string<\Throwable> $expected
 */
function expectError(string $expected, callable $action): void
{
    try {
        $action();
    } catch (\Throwable $error) {
        if (!$error instanceof $expected) {
            throw $error;
        }
        $detail = match (true) {
            $error instanceof PatternSyntax => "pattern {$error->pattern}",
            $error instanceof CacheWriteFailed => 'file ' . basename($error->path),
            $error instanceof RouteNotFound => "id {$error->id}",
            default => $error->getMessage(),
        };
        $kind = $error instanceof PuxException ? ' (PuxException)' : '';
        printf("%s%s: %s\n", (new \ReflectionClass($error))->getShortName(), $kind, $detail);

        return;
    }

    throw new \LogicException("No $expected was thrown.");
}

$mux = new Mux();
echo 'dispatch of an unknown path returns ', var_export($mux->dispatch('/missing'), true), "\n";

expectError(PatternSyntax::class, static fn () => $mux->any('/blog/(:id', 'post'));
expectError(
    PatternSyntax::class,
    static fn () => $mux->any('/blog/:id', 'post', ['require' => ['id' => '[0-9']]),
);
expectError(
    InvalidCallback::class,
    static fn () => RouteExecutor::execute([false, '/x', ['NoSuchController', 'indexAction'], []]),
);
$greetRoute = [false, '/greet', [GreetController::class, 'greetAction'], []];
expectError(MissingArgument::class, static fn () => RouteExecutor::execute($greetRoute));
expectError(CacheWriteFailed::class, static fn () => $mux->compile(__FILE__ . '/routes.php'));
expectError(RouteNotFound::class, static fn () => $mux->url('missing'));
expectError(\ValueError::class, static fn () => $mux->any('/x', 'x', ['method' => 'FETCH']));
expectError(
    \InvalidArgumentException::class,
    static fn () => RouteRequest::fromServer(['REQUEST_URI' => '/']),
);
```

```text
$ php eg/errors.php
dispatch of an unknown path returns NULL
PatternSyntax (PuxException): pattern /blog/(:id
PatternSyntax (PuxException): pattern /blog/:id
InvalidCallback (PuxException): Controller NoSuchController does not exist.
MissingArgument (PuxException): Cannot call Pux\Examples\GreetController::greetAction(): no route variable or route default for required parameter $name.
CacheWriteFailed (PuxException): file routes.php
RouteNotFound (PuxException): id missing
ValueError: "FETCH" is not a request method of Pux\RequestMethod; expected one of GET, POST, PUT, DELETE, PATCH, HEAD, OPTIONS.
InvalidArgumentException: The server array has no REQUEST_METHOD.
```

A path that matches no route is not an error: `dispatch()` returns `null`. Routing and
execution errors throw classes that implement the interface `Pux\Exception\PuxException`:

| Exception | Base class | Thrown when |
|---|---|---|
| `PatternSyntax` | `\InvalidArgumentException` | a pattern has an unbalanced parenthesis or a `require` expression makes it invalid (when the route is added); a `RouteRequest` predicate gets an invalid regular expression; the expression of a route fails at match time in `dispatch()` (an invalid expression from a cache file, the PCRE backtrack limit). The properties `pattern` and `detail` hold the text and the reason |
| `InvalidCallback` | `\LogicException` | `RouteExecutor::execute()` gets a callback that is not callable or names a missing class or method; `MuxCompiler` callback validation fails; `Controller::call()`, `runAction()` or `forward()` names a missing action |
| `MissingArgument` | `\LogicException` | a required parameter of the callback gets no value (see [Argument binding](#argument-binding)) |
| `CacheWriteFailed` | `\RuntimeException` | `Mux::compile()` or `MuxCompiler::compile()` cannot write the cache file; the property `path` holds the file |
| `RouteNotFound` | `\RuntimeException` | `url()` gets an id that no route has; the property `id` holds it |

Invalid arguments throw the standard PHP exceptions:

| Exception | Thrown when |
|---|---|
| `\ValueError` | the option `method` is not a `RequestMethod` case, name or value |
| `\InvalidArgumentException` | `RouteRequest::fromServer()` or `createFromEnv()` gets no method or path; a value of the server array is not a scalar; a route option has the wrong type |
| `\LogicException` | a mount closure returns a value that is not a `Mux`; `RESTfulMuxBuilder::addResource()` is called after `build()`; a mount point names a submux that the `Mux` does not hold |
| `\UnexpectedValueException` | `MuxCompiler::load()` reads a file that does not return a `Mux` |
| `\TypeError` | a route value cannot be converted to the parameter type |

## Testing your routes

<!-- example: eg/testing.php -->
```php
<?php

declare(strict_types=1);

/*
 * Testing routes: RouteRequest::create() builds a request from a method, a path and a server
 * array, so a test dispatches without $_SERVER and without a web server. The same calls work
 * inside a PHPUnit test with assertSame() in place of check().
 *
 *     php eg/testing.php   prints "3 checks passed"
 */

namespace Pux\Examples;

use Pux\Mux;
use Pux\RouteExecutor;
use Pux\RouteRequest;

require __DIR__ . '/../vendor/autoload.php';

function routes(): Mux
{
    $mux = new Mux();
    $mux->post('/orders/:id/cancel', static fn (int $id): string => "order $id cancelled", [
        'require' => ['id' => '\d+'],
        'secure' => true,
    ]);

    return $mux;
}

function check(mixed $expected, mixed $actual, string $what): void
{
    if ($expected !== $actual) {
        $expected = var_export($expected, true);
        $actual = var_export($actual, true);

        throw new \UnexpectedValueException("$what: expected $expected, got $actual.");
    }
}

$https = ['HTTP_HOST' => 'shop.example.com', 'HTTPS' => 'on'];

$path = '/orders/12/cancel';

$route = routes()->dispatchRequest(RouteRequest::create('POST', $path, $https));
check('order 12 cancelled', $route === null ? null : RouteExecutor::execute($route), 'HTTPS');
check(null, routes()->dispatchRequest(RouteRequest::create('POST', $path)), 'HTTP');
check(null, routes()->dispatchRequest(RouteRequest::create('GET', $path, $https)), 'GET');

echo "3 checks passed\n";
```

`RouteRequest::create()` takes the method, the path and the server array, so a test checks the
method, `domain` and `secure` constraints without `$_SERVER`. In a PHPUnit test, build the `Mux`
in the test or in a function that the application also uses, and assert on the result of
`dispatchRequest()` and `RouteExecutor::execute()`.

## Development

```sh
git clone https://github.com/dzahdev/PuxDZ.git
cd PuxDZ
composer install
composer check
```

| Command | What it runs |
|---|---|
| `composer test` | PHPUnit without the group `known-defect`; must pass |
| `composer test:red` | only the group `known-defect`: red tests of open defects, empty in 3.0 |
| `composer test:all` | every test |
| `composer test:unit -- --filter MuxTest` | one test class or method |
| `composer test:watch` | PHPUnit again on each change of a `.php` file in `src/` or `tests/` |
| `composer stan` | PHPStan: `src` at level 9, `tests` at level 5 |
| `composer cs` / `composer cs:fix` | PHP-CS-Fixer check (PSR-12, strict types) / fix |
| `composer readme` | `bin/check-readme-examples`: every `php` block of this README equals its file in `eg/` |
| `composer check` | `cs`, `stan`, `readme` and `test` in sequence |

On a machine whose PHP lacks `ext-dom` (PHPUnit needs it), `bin/test-in-docker` runs PHPUnit in
the image `php:8.3-cli`; `PHP_IMAGE=php:8.2-cli bin/test-in-docker` selects another version.
`bin/run-examples` runs every `eg/*.php` in Docker (or with the local `php` and `--native`) and
fails on a non-zero exit, output on stderr or output that differs from `eg/<name>.out`. CI runs
all of these on PHP 8.2, 8.3, 8.4 and 8.5.

Changes to `src/` are made test first, with tests at the public seams listed in
[ADR 0002](docs/adr/0002-tdd-at-seams.md): a failing test, the change that makes it pass, a
green `composer check`. [ADR 0001](docs/adr/0001-no-external-dependencies.md) keeps the package
free of runtime dependencies, and [GLOSSARY.md](GLOSSARY.md) defines the terms used in test
names and code (route, static route, PCRE route, mount, submux, expand).

## Upgrading from 2.x

[UPGRADE-3.0.md](UPGRADE-3.0.md) lists every incompatible change from the 2.0.x-dev branch
(package `corneltek/pux`) with the code before and after and what to change.
[CHANGES.md](CHANGES.md) lists all changes, with the defect IDs and upstream issue numbers.

## Credits and license

Pux was written by [Yo-An Lin](https://github.com/c9s) at
[c9s/Pux](https://github.com/c9s/Pux), with the contributors listed in
[CONTRIBUTORS.md](CONTRIBUTORS.md). PuxDZ is maintained by Denis Zakharov at
[dzahdev/PuxDZ](https://github.com/dzahdev/PuxDZ).

Released under the MIT License, see [LICENSE](LICENSE).
