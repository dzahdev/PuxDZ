<?php

declare(strict_types=1);

namespace Pux;

use Pux\Exception\InvalidCallback;

/**
 * Merges the routes of several Mux objects (or route cache files) into one Mux and writes it
 * to a route cache file.
 *
 * @phpstan-import-type Route from Mux
 */
final class MuxCompiler
{
    public Mux $mux;

    public int $idCounter = 0;

    public bool $callbackValidation = false;

    public function __construct()
    {
        $this->mux = new Mux();
    }

    public function get(): Mux
    {
        return $this->mux;
    }

    public function set(Mux $mux): void
    {
        $this->mux = $mux;
    }

    /**
     * Appends the routes of $mux; a mount point gets a new submux id in the compiler Mux.
     *
     * @return true
     *
     * @throws InvalidCallback with callbackValidation, see validateRouteCallback()
     * @throws \LogicException when a mount point names a submux id that $mux does not hold
     */
    public function merge(Mux $mux): bool
    {
        $routes = $mux->getRoutes();
        if ($this->callbackValidation) {
            $this->validateRouteCallback($routes);
        }

        foreach ($routes as $route) {
            if (is_int($route[2])) {
                // rewrite submux id
                $submux = $mux->getSubMux($route[2])
                    ?? throw new \LogicException(sprintf('The mount point %s names submux %d, which the merged Mux does not hold.', $route[1], $route[2]));
                $newId = ++$this->idCounter;
                $route[2] = $newId;
                $this->mux->submux[$newId] = $submux;
            }

            $this->mux->appendRouteArray($route);
        }

        return true;
    }

    /**
     * usort() comparator of routes: PCRE routes first, each group ordered by descending length
     * of the compiled expression (PCRE) or of the path (static).
     *
     * @param Route $a
     * @param Route $b
     */
    public static function sort_routes(array $a, array $b): int
    {
        // Equal lengths compare as 0; usort is stable since PHP 8.0, so such routes keep their
        // order of addition.
        if ($a[0] && $b[0]) {
            return strlen(self::compiled($b)) <=> strlen(self::compiled($a));
        } elseif ($a[0]) {
            return -1;
        } elseif ($b[0]) {
            return 1;
        }

        return strlen($b[1]) <=> strlen($a[1]);
    }

    /**
     * The option 'compiled' of a PCRE route.
     *
     * @param Route $route
     *
     * @throws \LogicException when it is absent or not a string
     */
    private static function compiled(array $route): string
    {
        $compiled = $route[3]['compiled'] ?? null;
        if (!is_string($compiled)) {
            throw new \LogicException(sprintf('The PCRE route %s has no compiled expression.', $route[1]));
        }

        return $compiled;
    }

    /**
     * Requires a route cache file (a PHP file that returns a Mux) and merges its routes.
     *
     * @param string $muxFile path of the route cache file
     *
     * @return Mux the compiler Mux with the routes of every file loaded so far
     *
     * @throws \UnexpectedValueException when the file does not return a Mux
     */
    public function load(string $muxFile): Mux
    {
        $mux = require $muxFile;
        if (!$mux instanceof Mux) {
            throw new \UnexpectedValueException(sprintf('The route file %s returns %s instead of a Pux\Mux.', $muxFile, get_debug_type($mux)));
        }
        $this->merge($mux);

        return $this->mux;
    }

    /**
     * Validate controller classes and controller methods before compiling to route cache.
     * The check reads the class only: no controller is instantiated, so constructors do not run.
     * A [class, method] callback needs an existing class and method, an [object, method]
     * callback an existing method of the object; other callbacks (closures, function names,
     * invokable objects, submux ids) are not checked.
     *
     * @param list<Route> $routes
     *
     * @throws InvalidCallback when an array callback names a missing class or method, or is not
     *                         a pair of a class name or an object and a method name
     */
    public function validateRouteCallback(array $routes): void
    {
        foreach ($routes as $route) {
            $callback = $route[2];
            if (!is_array($callback)) {
                continue;
            }

            [$target, $method] = array_values($callback) + [null, null];
            if (count($callback) !== 2 || !is_string($method) || !(is_string($target) || is_object($target))) {
                throw InvalidCallback::notCallable($callback);
            }
            if (is_string($target) && !class_exists($target, true)) {
                throw InvalidCallback::classNotFound($target);
            }

            if (!method_exists($target, $method)) {
                throw InvalidCallback::methodNotFound(is_object($target) ? $target::class : $target, $method);
            }
        }
    }

    /**
     * Compile merged routes to file.
     *
     * @throws Exception\CacheWriteFailed when the file cannot be written
     */
    public function compile(string $outFile): void
    {
        // compile routes to php file as a cache.
        $this->mux->sort();

        CacheFile::write($outFile, '<?php return ' . $this->mux->export() . '; /* version */');
    }
}
