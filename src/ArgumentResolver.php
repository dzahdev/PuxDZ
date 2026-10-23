<?php

declare(strict_types=1);

namespace Pux;

use ReflectionParameter;

/**
 * Binds route values to the parameters of an action, in parameter order: the route variable
 * of the same name, else a truthy route default of that name. A parameter that gets neither
 * is reported as unbound and left out of the argument list; the caller decides whether that
 * is an error (RouteExecutor::execute, Controller::call) or not (Controller::runAction).
 *
 * Not a seam (ADR 0002): covered through RouteExecutor::execute and Controller::call. Known
 * defects kept as they are until their own cycles: a falsy default is not bound (L6), and
 * binding is by name only (L7).
 */
final class ArgumentResolver
{
    /**
     * @param list<ReflectionParameter> $parameters
     * @param array<array-key, mixed>   $variables  route variables (route option 'vars')
     * @param array<array-key, mixed>   $defaults   route defaults (route option 'default')
     * @return array{list<mixed>, list<ReflectionParameter>} the bound arguments and the unbound parameters
     */
    public static function bind(array $parameters, array $variables, array $defaults): array
    {
        $arguments = [];
        $unbound = [];
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            if (isset($variables[$name])) {
                $arguments[] = $variables[$name];
            } elseif (isset($defaults[$name]) && $defaults[$name]) {
                $arguments[] = $defaults[$name];
            } else {
                $unbound[] = $parameter;
            }
        }

        return [$arguments, $unbound];
    }
}
