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
 * a required parameter without a value throws MissingArgument; a scalar parameter type converts
 * the value, see ArgumentResolver).
 *
 * @phpstan-import-type Route from Mux
 */
final class RouteExecutor
{
    /**
     * @param Route $route a route returned by Mux::dispatch()
     *
     * @return mixed the result of the callback
     *
     * @throws InvalidCallback when the callback is not callable or names a missing class or method
     * @throws \Pux\Exception\MissingArgument when a required parameter gets no value
     */
    public static function execute(array $route): mixed
    {
        [, , $callback, $options] = $route;

        $callable = self::resolveCallable($callback, self::arrayOption($options, 'constructor_args'));
        $function = new ReflectionFunction(Closure::fromCallable($callable));
        $scope = $function->getClosureScopeClass();
        $callableName = ($scope !== null ? $scope->getName() . '::' : '') . $function->getName();

        $arguments = ArgumentResolver::bind($function->getParameters(), self::arrayOption($options, 'vars') ?? [], self::arrayOption($options, 'default') ?? [], $callableName);

        return $callable(...$arguments);
    }

    /**
     * The route option $key, which is an array when it is present.
     *
     * @param array<string, mixed> $options
     *
     * @return array<array-key, mixed>|null null when the option is absent
     *
     * @throws \InvalidArgumentException when the option is present and not an array
     */
    private static function arrayOption(array $options, string $key): ?array
    {
        $value = $options[$key] ?? null;
        if ($value !== null && !is_array($value)) {
            throw new \InvalidArgumentException(sprintf('The route option %s is a %s; an array is expected.', $key, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed>|null $constructArgs arguments of the constructor of a [class name, method] callback
     *
     * @throws InvalidCallback when a class or a method is missing or the callback is not callable
     */
    private static function resolveCallable(mixed $callback, ?array $constructArgs): callable
    {
        if (is_array($callback) && count($callback) === 2 && is_string($callback[0] ?? null) && is_string($callback[1] ?? null)) {
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
