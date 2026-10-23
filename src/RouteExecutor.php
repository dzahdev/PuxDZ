<?php

namespace Pux;

use Exception;
use LogicException;
use ReflectionClass;
use Closure;
use Pux\Controller\Controller;

class RouteExecutor
{
    public static function execute(array $route)
    {
        [$pcre, $pattern, $callbackArg, $options] = $route;

        // create the reflection class
        $reflectionClass = new ReflectionClass( $callbackArg[0] );

        $constructArgs = $options['constructor_args'] ?? null;

        // if the first argument is a class name string,
        // then create the controller object.
        if (is_string($callbackArg[0])) {
            $callbackArg[0] = $controller = $constructArgs ? $reflectionClass->newInstanceArgs($constructArgs) : $reflectionClass->newInstance();
        } else {
            $controller = $callbackArg[0];
        }

        // check controller action method
        if ($controller && ! method_exists( $controller ,$callbackArg[1])) {
            throw new LogicException(sprintf('Controller action method \'%s\' doesn\'t exist.', $callbackArg[1]));
        }

        $rps = $reflectionClass->getMethod($callbackArg[1])->getParameters();

        [$arguments, $unbound] = ArgumentResolver::bind($rps, $options['vars'] ?? [], $options['default'] ?? []);
        foreach ($unbound as $rp) {
            if (! $rp->isOptional() && ! $rp->allowsNull()) {
                throw new Exception('parameter is not defined.');
            }
        }

        return call_user_func_array($callbackArg, $arguments);
    }
}
