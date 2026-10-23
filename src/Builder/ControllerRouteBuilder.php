<?php

namespace Pux\Builder;

use ReflectionClass;
use ReflectionMethod;
use Pux\Attribute\Route;
use Pux\RequestMethod;

/**
 * Builds routes from the public *Action methods of a controller.
 *
 * The route of an action is read from the attribute #[Pux\Attribute\Route(path, method:)].
 * Without the attribute, the docblock annotations @Route("/path") and @Method("GET") are read;
 * they are deprecated since 3.0 (see parseDocblockAnnotations()). When a method has the
 * attribute, its docblock is not read.
 */
class ControllerRouteBuilder
{
    /**
     * @param ReflectionMethod $reflectionMethod action method
     * @return array Annotation info: 'Route' (path) and 'Method' (request method name), each optional
     */
    protected static function parseMethodAnnotation(ReflectionMethod $reflectionMethod)
    {
        $attributes = $reflectionMethod->getAttributes(Route::class);
        if ($attributes !== []) {
            $route = $attributes[0]->newInstance();

            return $route->method === null
                ? ['Route' => $route->path]
                : ['Route' => $route->path, 'Method' => $route->method->name];
        }

        return self::parseDocblockAnnotations($reflectionMethod);
    }

    /**
     * Reads @Route("/path") and @Method("GET") from the docblock of an action.
     *
     * @deprecated since 3.0, use the attribute #[Pux\Attribute\Route] on the action; the
     *             docblock annotations are removed in 4.0
     *
     * @return array Annotation info: 'Route' (path) and 'Method' (request method name), each optional
     */
    private static function parseDocblockAnnotations(ReflectionMethod $reflectionMethod)
    {
        $annotations = [];
        if ($docComment = $reflectionMethod->getDocComment()) {
            if (preg_match('/^[\s*]*\@Method\("(get|put|post|delete|head|patch|options)"\)/im', (string) $docComment, $regs)) {
                $annotations['Method'] = $regs[1];
            }

            if (preg_match('/^[\s*]*\@Route\("([^\s]*)"\)/im', (string) $docComment, $regs)) {
                $annotations['Route'] = $regs[1];
            }
        }

        return $annotations;
    }

    /**
     * parseActionMethods parses the route definition from annotation and return
     * the "method" => "route meta" data structure
     *
     * Should always keep it returns simple array, so that we can cache the
     * data in somewhere...
     *
     * @return array
     */
    public static function parseActionMethods($con)
    {
        $reflectionClass = new ReflectionClass($con);
        $methodMap = [];

        // build up parent class list
        $parentClasses = [];
        $parentClasses[] = $reflectionClass;
        $parentClassRef = $reflectionClass;
        while ($parent = $parentClassRef->getParentClass()) {
            $parentClasses[] = $parent;
            $parentClassRef = $parent;
        }

        // iterate methods from parent class actions
        foreach (array_reverse($parentClasses) as $class) {
            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {

                // Ignore class methods that doesn't have Action in suffix
                if (!preg_match('/Action$/', $method->getName())) {
                    continue;
                }

                if (in_array($method->getName(), [ 'runAction', 'hasAction' ])) {
                    continue;
                }

                $meta = ['class' => $class->getName()];
                $annotations = self::parseMethodAnnotation($method);

                // If it's empty, then fetch annotations from parent methods
                if (empty($annotations)) {
                    if (isset($methodMap[$method->getName()])) {
                        $annotations = $methodMap[$method->getName()][0];
                    }
                }

                // always update method Map
                $methodMap[$method->getName()] = [$annotations, $meta];
            }
        }

        // TODO: see if we can cache it.
        return $methodMap;
    }

    /**
     * Translate action method name into route path.
     *
     * Upper case letters will be translated into slash + lower letter, e.g.
     *
     *      pageUpdateAction => /page/update
     *      fooAction => /foo
     *
     * @return string path
     */
    protected static function translatePath($methodName)
    {
        $methodName = preg_replace('/Action$/', '', (string) $methodName);
        return '/'.preg_replace_callback('/[A-Z]/', static fn($matches) => '/'.strtolower($matches[0]), $methodName);
    }

    /**
     * Return [["/path", "testAction", [ "method" => ... ] ],...].
     *
     * @return array returns routes array
     */
    public static function build($controller)
    {
        $routes = [];
        $actions = self::parseActionMethods($controller);

        foreach ($actions as $actionName => $actionInfo) {
            [$annotations, $meta] = $actionInfo;

            if (isset($annotations['Route'])) {
                $path = $annotations['Route'];
            } else {
                if ($actionName === 'indexAction') {
                    $path = '';
                } else {
                    $path = self::translatePath($actionName); // '/' . preg_replace_callback('/[A-Z]/', function($matches) {
                }
            }

            $route = [$path, $actionName];

            if (isset($annotations['Method'])) {
                $route[] = ['method' => RequestMethod::fromName($annotations['Method'])->value];
            } else {
                $route[] = [];
            }

            $routes[] = $route;
        }

        return $routes;
    }
}
