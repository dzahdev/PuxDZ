<?php

// vim:et:sw=4:ts=4:sts=4:
namespace Pux;
use Pux\Controller\ExpandableController;
use Pux\Controller\Controller;
use Pux\RouteRequest;
use Pux\Expandable;
use Closure;
use LogicException;
use IteratorAggregate;
use ArrayIterator;

/**
 * Mux class provides a built-in dispatch method that can dispatch routes,
 * You can define a Dispatcher to customze the dispatch logic on your own.
 *
 */
class Mux implements IteratorAggregate
{

    /**
     * @var array contains route array
     *
     * the route information is stored in the following format:
     *
     * [
     *    [ pcre (boolean), path (string), callback (callable), options (array) ],
     *    ....
     * ]
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
     */
    public $routes = [];

    public $routesById = [];

    /**
     * @var Mux[id]
     */
    public $submux = [];

    public $id;

    /**
     * @var bool expand routes to parent mux.
     *
     * When expand is enabled, all mounted Mux will expand the routes to the parent mux.
     * This improves the dispatch performance when you have a lot of sub mux to dispatch.
     *
     * When expand is enabled, the pattern comparison strategy for
     * strings will match the full string.
     *
     * When expand is disabled, the pattern comparison strategy for
     * strings will match the prefix.
     */
    public $expand = true;

    /**
     * @param Pux\Mux The parent mux object
     */
    protected $parent;

    public static $id_counter = 0;

    public static function generate_id()
    {
        return ++static::$id_counter;
    }

    public function getId()
    {
        if ($this->id) {
            return $this->id;
        }

        return $this->id = self::generate_id();
    }

    public function appendRouteArray(array $route)
    {
        $this->routes[] = $route;
    }

    public function appendRoute($pattern, $callback, array $options = [])
    {
        $this->routes[] = [false, $pattern, $callback, $options];
    }

    public function appendPCRERoute(array $routeArgs, $callback)
    {
        $this->routes[] = [
            true,
            // PCRE
            $routeArgs['compiled'],
            $callback,
            $routeArgs,
        ];
    }


    public function setParent(Mux $mux)
    {
        $this->parent = $mux;
    }

    /**
     * Mount routes on a path. The target is a Mux, an Expandable (for example an
     * ExpandableController, mounted through expand()), or a builder callable that receives a
     * new Mux to fill; a Closure builder may instead return the Mux to mount.
     */
    public function mount(string $pattern, Mux|Expandable|Closure|callable $target, array $options = [])
    {
        $mux = $target;

        // Save the mount path in options array
        $options['mount_path'] = $pattern;

        if ($mux instanceof Expandable) {

            $mux = $mux->expand($options);

        } else if ($mux instanceof Closure) {

            // we pass the newly created Mux object to the builder closure to initialize routes.
            if ($ret = $mux($mux = new Mux())) {
                if ($ret instanceof Mux) {
                    $mux = $ret;
                } else {
                    throw new LogicException('Invalid object returned from Closure.');
                }
            }
        } elseif ((!is_object($mux) || !($mux instanceof self)) && is_callable($mux)) {
            $mux($mux = new self());
        }

        // Route options hold no objects (they are exported): the submux is kept in
        // $this->submux, an expanded controller keeps its own Mux (ExpandableController::getMux()).
        $mux->setParent($this);


        if ($this->expand) {
            $pcre = str_contains($pattern,':');

            // rewrite submux routes
            foreach ($mux->routes as $route) {
                // process for pcre
                if ($route[0] || $pcre) {
                    $newPattern = $pattern . ( $route[0] ? $route[3]['pattern'] : $route[1] );
                    $routeArgs = PatternCompiler::compile($newPattern, 
                        array_replace_recursive($options, $route[3]) );
                    $this->appendPCRERoute( $routeArgs, $route[2] );
                } else {
                    $this->routes[] = [false, $pattern . $route[1], $route[2], isset($route[3]) ? array_replace_recursive($options, $route[3]) : $options];
                }
            }
        } else {

            $muxId = $mux->getId();
            $this->add($pattern, $muxId, $options);
            $this->submux[ $muxId ] = $mux;

        }
    }

    public function delete($pattern, $callback, array $options = [])
    {
        $options['method'] = RequestMethod::DELETE->value;
        $this->add($pattern, $callback, $options);
    }

    public function put($pattern, $callback, array $options = [])
    {
        $options['method'] = RequestMethod::PUT->value;
        $this->add($pattern, $callback, $options);
    }

    public function get($pattern, $callback, array $options = [])
    {
        $options['method'] = RequestMethod::GET->value;
        $this->add($pattern, $callback, $options);
    }

    public function post($pattern, $callback, array $options = [])
    {
        $options['method'] = RequestMethod::POST->value;
        $this->add($pattern, $callback, $options);
    }

    public function patch($pattern, $callback, array $options = [])
    {
        $options['method'] = RequestMethod::PATCH->value;
        $this->add($pattern, $callback, $options);
    }

    public function head($pattern, $callback, array $options = [])
    {
        $options['method'] = RequestMethod::HEAD->value;
        $this->add($pattern, $callback, $options);
    }

    public function options($pattern, $callback, array $options = [])
    {
        $options['method'] = RequestMethod::OPTIONS->value;
        $this->add($pattern, $callback, $options);
    }

    public function any($pattern, $callback, array $options = [])
    {
        $this->add($pattern, $callback, $options);
    }

    public function add($pattern, $callback, array $options = [])
    {
        if (is_string($callback) && str_contains($callback, ':')) {
            $callback = explode(':', $callback);
        }

        // Store the request method constraint as the integer value of its RequestMethod case.
        if (isset($options['method'])) {
            $options['method'] = self::requestMethodValue($options['method']);
        }

        // compile place holder to patterns
        $pcre = str_contains((string) $pattern, ':');
        if ($pcre) {
            $routeArgs = is_integer($callback)
                ? PatternCompiler::compilePrefix($pattern, $options)
                : PatternCompiler::compile($pattern, $options)
                ;

            // generate a pcre pattern route
            $route = [
                true,
                // PCRE
                $routeArgs['compiled'],
                $callback,
                $routeArgs,
            ];
            if (isset($options['id'])) {
                $this->routesById[ $options['id'] ] = $route;
            }

            return $this->routes[] = $route;
        } else {
            $route = [false, $pattern, $callback, $options];
            if (isset($options['id'])) {
                $this->routesById[ $options['id'] ] = $route;
            }

            // generate a simple string route.
            return $this->routes[] = $route;
        }
    }

    public function getRoute($id)
    {
        if (isset($this->routesById[$id])) {
            return $this->routesById[$id];
        }
    }

    public function sort()
    {
        usort($this->routes, \Pux\MuxCompiler::sort_routes(...));
    }

    public function compile($outFile, $sortBeforeCompile = true)
    {
        // compile routes to php file as a cache.
        if ($sortBeforeCompile) {
            $this->sort();
        }

        $code = '<?php return '.$this->export().';';

        return file_put_contents($outFile, $code);
    }

    public function getSubMux($id)
    {
        if (isset($this->submux[ $id ])) {
            return $this->submux[ $id ];
        }
    }

    /**
     * Integer value of the request method named $method, ignoring case.
     *
     * @deprecated since 3.0, use RequestMethod::fromName($method)->value
     *
     * @throws \ValueError when $method is not the name of a RequestMethod case (2.x returned 0)
     */
    public static function convertRequestMethodConstant($method)
    {
        return RequestMethod::fromName((string) $method)->value;
    }

    /**
     * The 'method' option as stored in a route: the integer value of a RequestMethod case, given
     * as the case, its name in any case or its value.
     *
     * @throws \ValueError for a name or a value that is not a RequestMethod case
     */
    private static function requestMethodValue($method)
    {
        return match (true) {
            $method instanceof RequestMethod => $method->value,
            is_int($method) => RequestMethod::from($method)->value,
            default => RequestMethod::fromName((string) $method)->value,
        };
    }

    /**
     * HTTPS is optional in the server array: absent, empty or 'off' (IIS) means plain HTTP.
     */
    private static function isHttps(RouteRequest $routeRequest)
    {
        $https = (string) ($routeRequest->serverParameters['HTTPS'] ?? '');

        return $https !== '' && strcasecmp($https, 'off') !== 0;
    }

    /**
     * Integer value of the request method, null for a method that is not a RequestMethod case
     * (such a request matches no route with a 'method' constraint).
     */
    private static function requestMethodOf(RouteRequest $routeRequest)
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
     * @param string       $path
     *
     * @return array
     */
    public function match($path, ?RouteRequest $routeRequest = null)
    {
        foreach ($this->routes as $route) {
            // If the route is using pcre pattern marching...
            if ($route[0]) {
                if (!preg_match($route[1], $path, $matches)) {
                    continue;
                }

                $route[3]['vars'] = $matches;

                // validate request method
                if (isset($route[3]['method']) && $route[3]['method'] != self::requestMethodOf($routeRequest ??= RouteRequest::fromServer())) {
                    continue;
                }

                if (isset($route[3]['domain']) && !($routeRequest ??= RouteRequest::fromServer())->hostEqual($route[3]['domain'])) {
                    continue;
                }

                if (isset($route[3]['secure']) && $route[3]['secure'] && !self::isHttps($routeRequest ??= RouteRequest::fromServer())) {
                    continue;
                }

                return $route;
            } else {
                // prefix match is used when expanding is not enabled.
                if ((
                        (is_int($route[2]) || $route[2] instanceof self || $route[2] instanceof Handler)
                        && strncmp((string) $route[1], $path, strlen((string) $route[1])) === 0
                    ) || $route[1] == $path) {
                    // validate request method
                    if (isset($route[3]['method']) && $route[3]['method'] != self::requestMethodOf($routeRequest ??= RouteRequest::fromServer())) {
                        continue;
                    }

                    if (isset($route[3]['domain']) && !($routeRequest ??= RouteRequest::fromServer())->hostEqual($route[3]['domain'])) {
                        continue;
                    }

                    if (isset($route[3]['secure']) && $route[3]['secure'] && !self::isHttps($routeRequest ??= RouteRequest::fromServer())) {
                        continue;
                    }

                    return $route;
                }

                continue;
            }
        }
    }

    public function dispatchRequest(RouteRequest $routeRequest)
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
     * @param string $path
     *
     * @return array
     */
    public function dispatch($path, ?RouteRequest $routeRequest = null)
    {
        if ($route = $this->match($path, $routeRequest)) {
            // When the callback is an integer, it's refereing to a submux object.
            if (is_integer($route[2])) {
                $submux = $this->submux[$route[2]];

                // sub-path and call submux to dispatch
                // for pcre pattern?
                if ($route[0]) {

                    $matchedString = $route[3]['vars'][0];
                    $subRoute = $submux->dispatch(substr($path, strlen((string) $matchedString)), $routeRequest);

                    // Named variables of the mount point reach the submux route; on a name
                    // clash the submux value wins. A static submux route has no 'vars' of its own.
                    if ($subRoute !== null) {
                        $mountVariables = array_filter($route[3]['vars'], 'is_string', ARRAY_FILTER_USE_KEY);
                        $subRoute[3]['vars'] = ($subRoute[3]['vars'] ?? []) + $mountVariables;
                    }

                    return $subRoute;

                } else {
                    return $submux->dispatch(
                        substr($path, strlen((string) $route[1])) ?: '',
                        $routeRequest
                    );
                }
            }

            return $route;
        }
    }

    public function length()
    {
        return count($this->routes);
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    public function setRoutes(array $routes)
    {
        $this->routes = $routes;
    }

    /**
     * PHP code that rebuilds this Mux through __set_state(): the keys id, routes, routesById and
     * submux, each submux exported the same way. The parent link is not exported, so a mounted
     * Mux exports without a circular reference. Routes are exported with var_export, so their
     * callbacks must be exportable (strings and arrays of strings, not closures).
     *
     * @return string
     */
    public function export()
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
     * @param string $id     route id
     * @param array  $params the parameters for an url
     *
     * @return string
     *
     * @throws \RuntimeException when no route has the id
     */
    public function url($id, array $params = [])
    {
        $route = $this->getRoute($id);

        if (!isset($route)) {
            throw new \RuntimeException('Named route not found for id: '.$id);
        }

        if (!$route[0]) {
            return $route[1];
        }

        $search = [];
        foreach ($params as $key => $value) {
            // try to match ':{key}' fragments and replace it with value
            $search[] = '#:'.preg_quote($key, '#').'\+?(?!\w)#';
        }

        $pattern = preg_replace($search, $params, (string) $route[3]['pattern']);

        // Remove remnants of unpopulated, trailing optional pattern segments, escaped special characters
        return preg_replace('#\(/?:.+\)|\(|\)|\\\\#', '', $pattern);
    }

    /**
     * Rebuilds a Mux from the array written by export(). 'routes' is required; 'id' (a Mux gets
     * an id only when it is mounted or asked for one), 'routesById' and 'submux' are optional.
     * Other keys (for example 'staticRoutes' and 'expand' of upstream cache files) are ignored.
     */
    public static function __set_state($array)
    {
        $self = new self();
        $self->routes = $array['routes'];
        $self->submux = $array['submux'] ?? [];
        $self->routesById = $array['routesById'] ?? [];
        $self->id = $array['id'] ?? null;

        return $self;
    }

    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new ArrayIterator($this->routes);
    }

}
