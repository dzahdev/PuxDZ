<?php

namespace Pux;

use Closure;
use Exception;
use Pux\Exception\InvalidCallback;
use ReflectionFunction;

/**
 * Runs the callback of a dispatched route.
 *
 * The callback is one of: a Closure, the name of a function, [class name, method] (the class
 * is instantiated, with options['constructor_args'] when given), [object, method], or an
 * invokable object. Mux::add() turns a 'Class:method' string into [class name, method].
 */
class RouteExecutor
{
    public static function execute(array $route)
    {
        [, , $callback, $options] = $route;

        $callable = self::resolveCallable($callback, $options['constructor_args'] ?? null);
        $parameters = (new ReflectionFunction(Closure::fromCallable($callable)))->getParameters();

        [$arguments, $unbound] = ArgumentResolver::bind($parameters, $options['vars'] ?? [], $options['default'] ?? []);
        foreach ($unbound as $rp) {
            if (! $rp->isOptional() && ! $rp->allowsNull()) {
                throw new Exception('parameter is not defined.');
            }
        }

        return $callable(...$arguments);
    }

    /**
     * @throws InvalidCallback when a class or a method is missing or the callback is not callable
     */
    private static function resolveCallable($callback, ?array $constructArgs): callable
    {
        if (is_array($callback) && count($callback) === 2 && is_string($callback[0])) {
            [$class, $method] = $callback;
            if (! class_exists($class)) {
                throw InvalidCallback::classNotFound($class);
            }
            if (! method_exists($class, $method)) {
                throw InvalidCallback::methodNotFound($class, $method);
            }
            $callback = [$constructArgs ? new $class(...$constructArgs) : new $class(), $method];
        }

        if (! is_callable($callback)) {
            throw InvalidCallback::notCallable($callback);
        }

        return $callback;
    }
}
