<?php

declare(strict_types=1);

namespace Pux;

use Pux\Exception\MissingArgument;
use ReflectionNamedType;
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
 * Scalar conversion: route variables are strings and the callers call the action under
 * strict_types (directly, not through call_user_func_array(), whose callback is called in
 * coercive mode when the name is resolved at run time in a namespace), so a value bound in steps 1-3 to a parameter declared with one scalar type
 * (int, float, string or bool, nullable or not) is converted when the conversion loses nothing:
 * an integer string to int, an int or a numeric string to float, an int or a float to string, a
 * boolean word of FILTER_VALIDATE_BOOL ("1", "0", "true", "false", "on", "off", "yes", "no",
 * "") to bool. Any other value is passed unchanged and PHP throws a TypeError for it.
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
                $arguments[$name] = self::convert($parameter, $remaining[$name]);
                unset($remaining[$name]);
            } else {
                $unbound[] = $parameter;
            }
        }

        $positional = array_values($remaining);
        foreach ($unbound as $index => $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($index, $positional)) {
                $arguments[$name] = self::convert($parameter, $positional[$index]);
            } elseif (array_key_exists($name, $defaults)) {
                $arguments[$name] = self::convert($parameter, $defaults[$name]);
            } elseif (!$parameter->isOptional()) {
                throw MissingArgument::forParameter($name, $callback);
            }
        }

        return $arguments;
    }

    /**
     * $value converted to the scalar type of $parameter when the conversion loses nothing;
     * otherwise $value unchanged.
     */
    private static function convert(ReflectionParameter $parameter, mixed $value): mixed
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
            return $value;
        }

        $converted = match ($type->getName()) {
            'int' => is_string($value) ? filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) : null,
            'float' => is_int($value) || (is_string($value) && is_numeric($value)) ? (float) $value : null,
            'string' => is_int($value) || is_float($value) ? (string) $value : null,
            'bool' => is_string($value) ? filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) : null,
            default => null,
        };

        return $converted ?? $value;
    }
}
