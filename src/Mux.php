<?php

declare(strict_types=1);

namespace Pux;

use ArrayIterator;
use Closure;
use IteratorAggregate;
use LogicException;

/**
 * Mux class provides a built-in dispatch method that can dispatch routes,
 * You can define a Dispatcher to customze the dispatch logic on your own.
 *
 * A route is the array [pcre, pattern, callback, options]: pcre is true for a pattern with a
 * variable, pattern is the path (static route) or the compiled expression (PCRE route).
 *
 * @phpstan-type Route array{0: bool, 1: string, 2: mixed, 3: array<string, mixed>}
 *
 * @implements IteratorAggregate<int, Route>
 */
final class Mux implements IteratorAggregate
{
    /**
     * The routes in the order of add() or of sort().
     *
     * The fields are in public scope because they are needed to be accessed by MuxCompiler.
     *
     * For each route item, you can define an option array for it, but there are some reserved keys:
     *
     *  - 'method': request method constraint
     *  - 'domain': host constraint
     *  - 'secure': https constraint
     *  - 'id'
     *  - 'requirements'
     *  - 'mount_path': the mounted path.
     *
     * @var list<Route>
     */
    public array $routes = [];

    /**
     * @var array<array-key, Route> named routes by the option 'id'
     */
    public array $routesById = [];

    /**
     * @var array<int, Mux> submux by id; a route whose callback is an integer dispatches to it
     */
    public array $submux = [];

    public ?int $id = null;

    /**
     * @var bool whether mount() copies the submux routes into this Mux; true by default.
     *
     * true (the default): mount() copies every submux route into this Mux with the mount
     * pattern as prefix, and dispatch() matches the full path against each copied route, with
     * no recursion into the submux.
     *
     * false: mount() adds one route for the mount pattern whose callback is the submux id
     * (the submux is kept in $submux). A static mount pattern matches as a prefix of the path,
     * a PCRE mount pattern is compiled with PatternCompiler::compilePrefix(); dispatch() then
     * dispatches the rest of the path in the submux.
     *
     * CHANGES.md of 2.0 describes expand as disabled; the code has always defaulted to true.
     */
    public bool $expand = true;

    public static int $id_counter = 0;

    public static function generate_id(): int
    {
        return ++self::$id_counter;
    }

    public function getId(): int
    {
        return $this->id ??= self::generate_id();
    }

    /**
     * @param Route $route
     */
    public function appendRouteArray(array $route): void
    {
        $this->routes[] = $route;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function appendRoute(string $pattern, mixed $callback, array $options = []): void
    {
        $this->routes[] = [false, $pattern, $callback, $options];
    }

    /**
     * @param array{compiled: string, ...<string, mixed>} $routeArgs a pattern compiled by PatternCompiler
     */
    public function appendPCRERoute(array $routeArgs, mixed $callback): void
    {
        $this->routes[] = [true, $routeArgs['compiled'], $callback, $routeArgs];
    }

    /**
     * Mount routes on a path. The target is a Mux, an Expandable (for example an
     * ExpandableController, mounted through expand()), or a builder callable that receives a
     * new Mux to fill; a Closure builder may instead return the Mux to mount.
     *
     * @param array<string, mixed> $options
     *
     * @throws LogicException when a Closure returns a value that is not a Mux
     */
    public function mount(string $pattern, Mux|Expandable|Closure|callable $target, array $options = []): void
    {
        // Save the mount path in options array
        $options['mount_path'] = $pattern;

        if ($target instanceof self) {
            $mux = $target;
        } elseif ($target instanceof Expandable) {
            $mux = $target->expand($options);
        } elseif ($target instanceof Closure) {
            // we pass the newly created Mux object to the builder closure to initialize routes.
            $mux = new self();
            $ret = $target($mux);
            if ($ret) {
                if (!$ret instanceof self) {
                    throw new LogicException('Invalid object returned from Closure.');
                }
                $mux = $ret;
            }
        } else {
            $mux = new self();
            $target($mux);
        }

        // Route options hold no objects (they are exported): the submux is kept in
        // $this->submux, an expanded controller keeps its own Mux (ExpandableController::getMux()).
        if ($this->expand) {
            $pcre = str_contains($pattern, ':');

            // rewrite submux routes
            foreach ($mux->routes as $route) {
                if ($route[0] || $pcre) {
                    $newPattern = $pattern . ($route[0] ? self::stringOption($route[3], 'pattern') : $route[1]);
                    $routeArgs = PatternCompiler::compile($newPattern, array_replace_recursive($options, $route[3]));
                    $this->appendPCRERoute($routeArgs, $route[2]);
                } else {
                    $this->routes[] = [false, $pattern . $route[1], $route[2], array_replace_recursive($options, $route[3])];
                }
            }
        } else {
            $muxId = $mux->getId();
            $this->add($pattern, $muxId, $options);
            $this->submux[$muxId] = $mux;
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    public function delete(string $pattern, mixed $callback, array $options = []): void
    {
        $options['method'] = RequestMethod::DELETE->value;
        $this->add($pattern, $callback, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function put(string $pattern, mixed $callback, array $options = []): void
    {
        $options['method'] = RequestMethod::PUT->value;
        $this->add($pattern, $callback, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function get(string $pattern, mixed $callback, array $options = []): void
    {
        $options['method'] = RequestMethod::GET->value;
        $this->add($pattern, $callback, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function post(string $pattern, mixed $callback, array $options = []): void
    {
        $options['method'] = RequestMethod::POST->value;
        $this->add($pattern, $callback, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function patch(string $pattern, mixed $callback, array $options = []): void
    {
        $options['method'] = RequestMethod::PATCH->value;
        $this->add($pattern, $callback, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function head(string $pattern, mixed $callback, array $options = []): void
    {
        $options['method'] = RequestMethod::HEAD->value;
        $this->add($pattern, $callback, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function options(string $pattern, mixed $callback, array $options = []): void
    {
        $options['method'] = RequestMethod::OPTIONS->value;
        $this->add($pattern, $callback, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function any(string $pattern, mixed $callback, array $options = []): void
    {
        $this->add($pattern, $callback, $options);
    }

    /**
     * Adds a route and returns it. A pattern with ':' is a PCRE route; an integer callback is
     * the id of a submux (mount() without expand), whose PCRE pattern is compiled as a prefix.
     *
     * @param array<string, mixed> $options
     *
     * @return Route
     *
     * @throws \ValueError for a 'method' option that is not a RequestMethod case
     */
    public function add(string $pattern, mixed $callback, array $options = []): array
    {
        // 'Class::method' and 'Class:method' are stored as [class, method].
        if (is_string($callback) && str_contains($callback, ':')) {
            $callback = explode(str_contains($callback, '::') ? '::' : ':', $callback, 2);
        }

        // Store the request method constraint as the integer value of its RequestMethod case.
        if (isset($options['method'])) {
            $options['method'] = self::requestMethodValue($options['method']);
        }

        if (str_contains($pattern, ':')) {
            $routeArgs = is_int($callback)
                ? PatternCompiler::compilePrefix($pattern, $options)
                : PatternCompiler::compile($pattern, $options);
            $route = [true, $routeArgs['compiled'], $callback, $routeArgs];
        } else {
            $route = [false, $pattern, $callback, $options];
        }

        if (isset($options['id'])) {
            $this->routesById[self::routeId($options['id'])] = $route;
        }

        return $this->routes[] = $route;
    }

    /**
     * @return Route|null the route added with the option 'id' => $id; null when there is none
     */
    public function getRoute(int|string $id): ?array
    {
        return $this->routesById[$id] ?? null;
    }

    public function sort(): void
    {
        usort($this->routes, MuxCompiler::sort_routes(...));
    }

    /**
     * Writes the routes (sorted first, by default) to $outFile as PHP code that returns this Mux.
     *
     * @throws Exception\CacheWriteFailed when the file cannot be written
     */
    public function compile(string $outFile, bool $sortBeforeCompile = true): void
    {
        // compile routes to php file as a cache.
        if ($sortBeforeCompile) {
            $this->sort();
        }

        CacheFile::write($outFile, '<?php return ' . $this->export() . ';');
    }

    public function getSubMux(int $id): ?Mux
    {
        return $this->submux[$id] ?? null;
    }

    /**
     * Integer value of the request method named $method, ignoring case.
     *
     * @deprecated since 3.0, use RequestMethod::fromName($method)->value
     *
     * @throws \ValueError when $method is not the name of a RequestMethod case (2.x returned 0)
     */
    public static function convertRequestMethodConstant(string $method): int
    {
        return RequestMethod::fromName($method)->value;
    }

    /**
     * The 'method' option as stored in a route: the integer value of a RequestMethod case, given
     * as the case, its name in any case or its value.
     *
     * @throws \ValueError for a name or a value that is not a RequestMethod case
     */
    private static function requestMethodValue(mixed $method): int
    {
        return match (true) {
            $method instanceof RequestMethod => $method->value,
            is_int($method) => RequestMethod::from($method)->value,
            is_string($method) => RequestMethod::fromName($method)->value,
            default => throw new \ValueError(sprintf('The option method is a %s; a RequestMethod, its name or its value is expected.', get_debug_type($method))),
        };
    }

    /**
     * The option 'id' as a key of $routesById.
     *
     * @throws \InvalidArgumentException when it is not an integer or a string
     */
    private static function routeId(mixed $id): int|string
    {
        if (!is_int($id) && !is_string($id)) {
            throw new \InvalidArgumentException(sprintf('The option id is a %s; an integer or a string is expected.', get_debug_type($id)));
        }

        return $id;
    }

    /**
     * A string option that a route must have, such as 'pattern' of a PCRE route.
     *
     * @param array<string, mixed> $options
     *
     * @throws LogicException when the option is absent or not a string
     */
    private static function stringOption(array $options, string $key): string
    {
        $value = $options[$key] ?? null;
        if (!is_string($value)) {
            throw new LogicException(sprintf('The route option %s is a %s; a string is expected.', $key, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * HTTPS is optional in the server array: absent, empty or 'off' (IIS) means plain HTTP.
     */
    private static function isHttps(RouteRequest $routeRequest): bool
    {
        $https = $routeRequest->serverParameters['HTTPS'] ?? '';
        if (!is_scalar($https)) {
            throw new \InvalidArgumentException(sprintf('The server parameter HTTPS is a %s; a string is expected.', get_debug_type($https)));
        }
        $https = (string) $https;

        return $https !== '' && strcasecmp($https, 'off') !== 0;
    }

    /**
     * Integer value of the request method, null for a method that is not a RequestMethod case
     * (such a request matches no route with a 'method' constraint).
     */
    private static function requestMethodOf(RouteRequest $routeRequest): ?int
    {
        return RequestMethod::tryFromName($routeRequest->getRequestMethod())?->value;
    }

    /**
     * Find a matched route with the path constraint in the current mux object.
     *
     * The request method, host and HTTPS come from $routeRequest. A 'domain' constraint is
     * compared with RouteRequest::hostEqual (no HTTP_HOST means no match). A route with
     * 'secure' => true requires HTTPS (the HTTPS server key non-empty and not 'off'); false or
     * absent is no constraint. Without $routeRequest, the request is built with
     * RouteRequest::fromServer() when the first route with a 'method', 'domain' or 'secure'
     * constraint matches the path, so routes without constraints match without any request; fromServer throws
     * \InvalidArgumentException when the server array has no REQUEST_METHOD (the CLI).
     *
     * @return Route|null the route, a PCRE route with its captures in the option 'vars'; null
     *                    when no route matches
     *
     * @throws Exception\PatternSyntax when the expression of a PCRE route fails to match (an
     *                                  invalid expression from a cache file, the backtrack limit)
     */
    public function match(string $path, ?RouteRequest $routeRequest = null): ?array
    {
        $found = $this->find($path, $routeRequest);

        return $found === null ? null : $found[0];
    }

    /**
     * match() plus the captures of a PCRE route (empty for a static route).
     *
     * @return array{Route, array<int|string, string>}|null
     */
    private function find(string $path, ?RouteRequest &$routeRequest): ?array
    {
        foreach ($this->routes as $route) {
            $matches = [];
            if ($route[0]) {
                // A PCRE failure at match time (for example the backtrack limit) throws
                // PatternSyntax instead of reading as "no match".
                if (!Pcre::match($route[1], $path, $matches)) {
                    continue;
                }
                $route[3]['vars'] = $matches;
            } elseif (!self::matchesStaticPath($route, $path)) {
                continue;
            }

            if (self::meetsConstraints($route[3], $routeRequest)) {
                return [$route, $matches];
            }
        }

        return null;
    }

    /**
     * A static route matches its path exactly; a static mount point (submux id, Mux or Handler
     * callback) matches the paths that start with it, because expanding is not enabled.
     *
     * @param Route $route
     */
    private static function matchesStaticPath(array $route, string $path): bool
    {
        if (is_int($route[2]) || $route[2] instanceof self || $route[2] instanceof Handler) {
            return str_starts_with($path, $route[1]);
        }

        return $route[1] === $path;
    }

    /**
     * The 'method', 'domain' and 'secure' constraints of a route; the request is built from the
     * server superglobal on the first constraint that needs it (see match()).
     *
     * @param array<string, mixed> $options
     */
    private static function meetsConstraints(array $options, ?RouteRequest &$routeRequest): bool
    {
        if (isset($options['method']) && $options['method'] != self::requestMethodOf($routeRequest ??= RouteRequest::fromServer())) {
            return false;
        }

        if (isset($options['domain']) && !($routeRequest ??= RouteRequest::fromServer())->hostEqual(self::stringOption($options, 'domain'))) {
            return false;
        }

        if (isset($options['secure']) && $options['secure'] && !self::isHttps($routeRequest ??= RouteRequest::fromServer())) {
            return false;
        }

        return true;
    }

    /**
     * @return Route|null
     */
    public function dispatchRequest(RouteRequest $routeRequest): ?array
    {
        return $this->dispatch($routeRequest->getPath(), $routeRequest);
    }

    /**
     * Match route in the current Mux and submuxes recursively.
     *
     * The RouteRequest object is used for serving request method, host name
     * and other route information; it is passed on to the submux. Without it, a request is
     * built from the server superglobal only when a route with a constraint matches the path
     * (see match()).
     *
     * @return Route|null
     *
     * @throws LogicException when a mount point names a submux id that this Mux does not hold
     * @throws Exception\PatternSyntax when the expression of a PCRE route fails to match
     */
    public function dispatch(string $path, ?RouteRequest $routeRequest = null): ?array
    {
        $found = $this->find($path, $routeRequest);
        if ($found === null) {
            return null;
        }
        [$route, $matches] = $found;

        // When the callback is an integer, it's refereing to a submux object.
        if (!is_int($route[2])) {
            return $route;
        }

        $submux = $this->submux[$route[2]] ?? throw new LogicException(sprintf('The mount point %s names submux %d, which this Mux does not hold.', $route[1], $route[2]));

        if (!$route[0]) {
            return $submux->dispatch(substr($path, strlen($route[1])), $routeRequest);
        }

        // sub-path and call submux to dispatch
        $subRoute = $submux->dispatch(substr($path, strlen($matches[0])), $routeRequest);

        // Named variables of the mount point reach the submux route; on a name
        // clash the submux value wins. A static submux route has no 'vars' of its own.
        if ($subRoute !== null) {
            $subVariables = $subRoute[3]['vars'] ?? [];
            if (!is_array($subVariables)) {
                throw new LogicException(sprintf('The route option vars is a %s; an array is expected.', get_debug_type($subVariables)));
            }
            $subRoute[3]['vars'] = $subVariables + array_filter($matches, is_string(...), ARRAY_FILTER_USE_KEY);
        }

        return $subRoute;
    }

    public function length(): int
    {
        return count($this->routes);
    }

    /**
     * @return list<Route>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @param list<Route> $routes
     */
    public function setRoutes(array $routes): void
    {
        $this->routes = $routes;
    }

    /**
     * PHP code that rebuilds this Mux through __set_state(): the keys id, routes, routesById and
     * submux, each submux exported the same way. Routes are exported with var_export, so their
     * callbacks must be exportable (strings and arrays of strings, not closures).
     */
    public function export(): string
    {
        $submux = '';
        foreach ($this->submux as $id => $mux) {
            $submux .= sprintf("    %s => %s,\n", var_export($id, true), $mux->export());
        }

        return sprintf(
            "\\%s::__set_state([\n'id' => %s,\n'routes' => %s,\n'routesById' => %s,\n'submux' => [\n%s],\n])",
            self::class,
            var_export($this->id, true),
            var_export($this->routes, true),
            var_export($this->routesById, true),
            $submux,
        );
    }

    /**
     * url method generates the related URL for a route.
     *
     * A static route returns its path. A PCRE route replaces each ':name' of its source pattern
     * with $params['name'] and drops optional groups whose variables are not given. Values are
     * inserted as given: the caller encodes them (rawurlencode for a path segment).
     *
     * @see https://github.com/c9s/Pux/issues/4
     *
     * @param int|string                    $id     route id
     * @param array<string, scalar|\Stringable> $params the parameters for an url
     *
     * @throws \RuntimeException when no route has the id
     */
    public function url(int|string $id, array $params = []): string
    {
        $route = $this->getRoute($id);

        if ($route === null) {
            throw new \RuntimeException('Named route not found for id: ' . $id);
        }

        if (!$route[0]) {
            return $route[1];
        }

        $search = [];
        $replace = [];
        foreach ($params as $key => $value) {
            // try to match ':{key}' fragments and replace it with value
            $search[] = '#:' . preg_quote($key, '#') . '\+?(?!\w)#';
            $replace[] = (string) $value;
        }

        $pattern = preg_replace($search, $replace, self::stringOption($route[3], 'pattern'))
            ?? throw new \RuntimeException('Cannot insert the parameters into the route pattern: ' . preg_last_error_msg());

        // Remove remnants of unpopulated, trailing optional pattern segments, escaped special characters
        return preg_replace('#\(/?:.+\)|\(|\)|\\\\#', '', $pattern)
            ?? throw new \RuntimeException('Cannot remove the optional groups from the route pattern: ' . preg_last_error_msg());
    }

    /**
     * Rebuilds a Mux from the array written by export(). 'routes' is required; 'id' (a Mux gets
     * an id only when it is mounted or asked for one), 'routesById' and 'submux' are optional.
     * Other keys (for example 'staticRoutes' and 'expand' of upstream cache files) are ignored.
     *
     * @param array{routes: list<Route>, id?: int|null, routesById?: array<array-key, Route>, submux?: array<int, Mux>} $array
     */
    public static function __set_state(array $array): self
    {
        $self = new self();
        $self->routes = $array['routes'];
        $self->submux = $array['submux'] ?? [];
        $self->routesById = $array['routesById'] ?? [];
        $self->id = $array['id'] ?? null;

        return $self;
    }

    /**
     * @return ArrayIterator<int, Route>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->routes);
    }
}
