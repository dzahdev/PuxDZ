<?php

declare(strict_types=1);

namespace Pux;

use Pux\Exception\MissingArgument;
use ReflectionParameter;

/**
 * Binds route values to the parameters of an action. One rule for RouteExecutor::execute,
 * Controller::call and Controller::runAction:
 *
 * 1. By name: a parameter takes the route variable of the same name.
 * 2. By position: each parameter left unbound by step 1, in parameter order, takes the next
 *    named route variable (string key of 'vars', in the order of 'vars', which is pattern order)
 *    that step 1 did not take. Numeric keys of 'vars' (the full match and the numbered groups
 *    of preg_match) are never bound.
 * 3. Route default: a parameter still unbound takes the route default (option 'default') of
 *    its name; falsy values are bound too.
 * 4. Own default: an optional parameter still unbound is left out, so PHP uses its default
 *    value; a variadic parameter takes no route value.
 * 5. Otherwise the parameter is required and MissingArgument names it and the callback. A
 *    parameter that allows null is still required when it has no default value.
 *
 * The result is keyed by parameter name and is passed as named arguments, so a parameter left
 * out in step 4 does not shift the ones after it.
 *
 * Not a seam (ADR 0002): covered through RouteExecutor::execute, Controller::call and
 * Controller::runAction.
 */
final class ArgumentResolver
{
    /**
     * @param list<ReflectionParameter> $parameters
     * @param array<array-key, mixed>   $variables  route variables (route option 'vars')
     * @param array<array-key, mixed>   $defaults   route defaults (route option 'default')
     * @param string                    $callback   callback name for the MissingArgument message
     *
     * @return array<string, mixed> arguments keyed by parameter name
     *
     * @throws MissingArgument when a required parameter gets no value
     */
    public static function bind(array $parameters, array $variables, array $defaults, string $callback): array
    {
        $remaining = array_filter($variables, is_string(...), ARRAY_FILTER_USE_KEY);
        $arguments = [];
        $unbound = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            if ($parameter->isVariadic()) {
                continue;
            }
            if (array_key_exists($name, $remaining)) {
                $arguments[$name] = $remaining[$name];
                unset($remaining[$name]);
            } else {
                $unbound[] = $parameter;
            }
        }

        $positional = array_values($remaining);
        foreach ($unbound as $index => $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($index, $positional)) {
                $arguments[$name] = $positional[$index];
            } elseif (array_key_exists($name, $defaults)) {
                $arguments[$name] = $defaults[$name];
            } elseif (!$parameter->isOptional()) {
                throw MissingArgument::forParameter($name, $callback);
            }
        }

        return $arguments;
    }
}
