<?php

namespace Pux;

use Pux\Exception\InvalidCallback;
use ReflectionClass;

class MuxCompiler
{
    public $mux;

    public $idCounter;

    public $callbackValidation = false;

    public function __construct()
    {
        $this->idCounter = 0;
        $this->mux = new Mux();
    }

    public function get()
    {
        return $this->mux;
    }

    public function set($mux)
    {
        $this->mux = $mux;
    }

    public function merge($mux)
    {
        $routes = $mux->getRoutes();
        if ($this->callbackValidation) {
            $this->validateRouteCallback($routes);
        }

        foreach ($routes as $route) {
            if (is_int($route[2])) {
                // rewrite submux id
                $submux = $mux->getSubMux($route[2]);
                $newId = ++$this->idCounter;
                $route[2] = $newId;
                $this->mux->submux[ $newId ] = $submux;
            }

            $this->mux->appendRouteArray($route);
        }

        return true;
    }

    public static function sort_routes($a, $b)
    {
        // PCRE routes first, each group ordered by descending length of the compiled
        // expression (PCRE) or of the path (static). Equal lengths compare as 0; usort is
        // stable since PHP 8.0, so such routes keep their order of addition.
        if ($a[0] && $b[0]) {
            return strlen((string) $b[3]['compiled']) <=> strlen((string) $a[3]['compiled']);
        } elseif ($a[0]) {
            return -1;
        } elseif ($b[0]) {
            return 1;
        }

        return strlen((string) $b[1]) <=> strlen((string) $a[1]);
    }

    /**
     * Requires a route cache file (a PHP file that returns a Mux) and merges its routes.
     *
     * @param string $muxFile path of the route cache file
     *
     * @return Mux the compiler Mux with the routes of every file loaded so far
     */
    public function load($muxFile)
    {
        $mux = require $muxFile;
        $this->merge($mux);

        return $this->mux;
    }

    /**
     * Validate controller classes and controller methods before compiling to route cache.
     * The check reads the class only: no controller is instantiated, so constructors do not run.
     *
     * @throws InvalidCallback when a [class, method] callback names a missing class or method
     */
    public function validateRouteCallback(array $routes)
    {
        foreach ($routes as $route) {
            $callback = $route[2];
            if (is_array($callback)) {
                $class = $callback[0];
                $method = $callback[1];
                if (!class_exists($class, true)) {
                    throw InvalidCallback::classNotFound($class);
                }

                if (!method_exists($class, $method)) {
                    throw InvalidCallback::methodNotFound($class, $method);
                }
            }
        }
    }

    public function compileReflectionParameters()
    {
        foreach ($this->mux->routes as $route) {
            [$class, $method] = $route[2];

            $refClass = new ReflectionClass($class);
            $refMethod = $refClass->getMethod($method);
            $refParameters = $refMethod->getParameters();

            // HHVM does not support Reflection currently.
            if (!defined('HHVM_VERSION')) {
                // when __reflection is defined, we re-use the reflection info.
                $route[3]['__reflection'] = [];
                foreach ($refParameters as $refParameter) {
                    // ReflectionParameter
                    $param = ['name' => $refParameter->getName(), 'position' => $refParameter->getPosition()];
                    if ($refParameter->isDefaultValueAvailable()) {
                        $param['default'] = $refParameter->getDefaultValue();
                        if (PHP_VERSION_ID >= 50406 && $refParameter->isDefaultValueConstant()) {
                            $param['constant'] = $refParameter->getDefaultValueConstantName();
                        }
                    }

                    if ($optional = $refParameter->isOptional()) {
                        $param['optional'] = $optional;
                    }

                    $route[3]['__reflection']['params'][] = $param;
                }
            }
        }
    }

    /**
     * Compile merged routes to file.
     */
    public function compile($outFile)
    {
        // compile routes to php file as a cache.
        usort($this->mux->routes, \Pux\MuxCompiler::sort_routes(...));

        $code = $this->mux->export();

        return file_put_contents($outFile, '<?php return '.$code.'; /* version */');
    }
}
