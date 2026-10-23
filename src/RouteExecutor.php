<?php

declare(strict_types=1);

namespace Pux;

use Closure;
use Pux\Exception\InvalidCallback;
use ReflectionFunction;

/**
 * Runs the callback of a dispatched route.
 *
 * The callback is one of: a Closure, the name of a function, [class name, method] (the class
 * is instantiated, with options['constructor_args'] when given), [object, method], or an
 * invokable object. Mux::add() turns a 'Class:method' string into [class name, method].
 * Arguments are bound by ArgumentResolver (by name, by position, route default, own default;
 * a required parameter without a value throws MissingArgument).
 */
class RouteExecutor
{
    public static function execute(array $route)
    {
        [, , $callback, $options] = $route;

        $callable = self::resolveCallable($callback, $options['constructor_args'] ?? null);
        $function = new ReflectionFunction(Closure::fromCallable($callable));
        $scope = $function->getClosureScopeClass();
        $callableName = ($scope !== null ? $scope->getName() . '::' : '') . $function->getName();

        $arguments = ArgumentResolver::bind($function->getParameters(), $options['vars'] ?? [], $options['default'] ?? [], $callableName);

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
