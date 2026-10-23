<?php

declare(strict_types=1);

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
 *
 * @phpstan-type Annotations array{Route?: string, Method?: string}
 */
final class ControllerRouteBuilder
{
    /**
     * @param ReflectionMethod $reflectionMethod action method
     *
     * @return Annotations 'Route' (path) and 'Method' (request method name), each optional
     */
    private static function parseMethodAnnotation(ReflectionMethod $reflectionMethod): array
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
     * @return Annotations 'Route' (path) and 'Method' (request method name), each optional
     */
    private static function parseDocblockAnnotations(ReflectionMethod $reflectionMethod): array
    {
        $annotations = [];
        $docComment = $reflectionMethod->getDocComment();
        if ($docComment !== false) {
            if (preg_match('/^[\s*]*\@Method\("(get|put|post|delete|head|patch|options)"\)/im', $docComment, $regs)) {
                $annotations['Method'] = $regs[1];
            }

            if (preg_match('/^[\s*]*\@Route\("([^\s]*)"\)/im', $docComment, $regs)) {
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
     * @param object|class-string $con controller object or class name
     *
     * @return array<string, array{Annotations, array{class: class-string}}> action method name =>
     *         [annotations, ['class' => declaring class]]; an override without annotations keeps
     *         the annotations of the parent method
     */
    public static function parseActionMethods(object|string $con): array
    {
        $reflectionClass = new ReflectionClass($con);
        $methodMap = [];

        // build up parent class list
        $parentClasses = [$reflectionClass];
        $parentClassRef = $reflectionClass;
        while ($parent = $parentClassRef->getParentClass()) {
            $parentClasses[] = $parent;
            $parentClassRef = $parent;
        }

        // iterate methods from parent class actions
        foreach (array_reverse($parentClasses) as $class) {
            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $name = $method->getName();

                // Ignore class methods that doesn't have Action in suffix
                if (!str_ends_with($name, 'Action') || in_array($name, ['runAction', 'hasAction'], true)) {
                    continue;
                }

                $annotations = self::parseMethodAnnotation($method);

                // If it's empty, then fetch annotations from parent methods
                if ($annotations === [] && isset($methodMap[$name])) {
                    $annotations = $methodMap[$name][0];
                }

                // always update method Map
                $methodMap[$name] = [$annotations, ['class' => $class->getName()]];
            }
        }

        return $methodMap;
    }

    /**
     * Translate action method name into route path.
     *
     * Upper case letters will be translated into slash + lower letter, e.g.
     *
     *      pageUpdateAction => /page/update
     *      fooAction => /foo
     */
    private static function translatePath(string $methodName): string
    {
        if (str_ends_with($methodName, 'Action')) {
            $methodName = substr($methodName, 0, -strlen('Action'));
        }

        return '/' . preg_replace_callback('/[A-Z]/', static fn (array $matches): string => '/' . strtolower($matches[0]), $methodName);
    }

    /**
     * Return [["/path", "testAction", [ "method" => ... ] ],...].
     *
     * @param object|class-string $controller controller object or class name
     *
     * @return list<array{string, string, array{method?: int}}> one route per action: path,
     *         action method name, options
     */
    public static function build(object|string $controller): array
    {
        $routes = [];

        foreach (self::parseActionMethods($controller) as $actionName => [$annotations]) {
            $path = $annotations['Route'] ?? ($actionName === 'indexAction' ? '' : self::translatePath($actionName));

            $routes[] = [
                $path,
                $actionName,
                isset($annotations['Method']) ? ['method' => RequestMethod::fromName($annotations['Method'])->value] : [],
            ];
        }

        return $routes;
    }
}
