<?php

namespace Pux\Tests\ControllerRouteBuilder;

use CRUDProductController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ProductResourceController;
use Pux\Builder\ControllerRouteBuilder;
use Pux\Tests\Fixtures\AnnotatedActionsController;
use Pux\Tests\Fixtures\ChildController;

/**
 * ControllerRouteBuilder::build turns the public *Action methods of a controller into
 * [path, actionName, options] entries. Method constraints are integers: 1 = GET, 2 = POST,
 * 3 = PUT, 4 = DELETE (the REQUEST_METHOD_* constants).
 */
final class BuildTest extends TestCase
{
    /**
     * @param list<array{string, string, array<string, mixed>}> $routes
     * @return array{string, string, array<string, mixed>}
     */
    private static function routeOf(array $routes, string $action): array
    {
        foreach ($routes as $route) {
            if ($route[1] === $action) {
                return $route;
            }
        }

        self::fail(sprintf('build() returned no route for %s', $action));
    }

    public function test_build_returns_one_route_per_action_in_declaration_order(): void
    {
        self::assertSame(
            [
                ['', 'indexAction', []],
                ['/item', 'itemAction', ['method' => 1]],
                ['/add', 'addAction', ['method' => 2]],
                ['/update', 'updateAction', ['method' => 3]],
                ['/delete', 'delAction', ['method' => 4]],
                ['/foo/bar', 'fooBarAction', []],
            ],
            ControllerRouteBuilder::build(new AnnotatedActionsController()),
        );
    }

    public function test_build_maps_camel_case_action_name_to_slash_separated_path(): void
    {
        $routes = ControllerRouteBuilder::build(new AnnotatedActionsController());

        self::assertSame(['/foo/bar', 'fooBarAction', []], self::routeOf($routes, 'fooBarAction'));
    }

    public function test_build_maps_index_action_to_empty_path(): void
    {
        $routes = ControllerRouteBuilder::build(new AnnotatedActionsController());

        self::assertSame(['', 'indexAction', []], self::routeOf($routes, 'indexAction'));
    }

    public function test_build_uses_route_annotation_as_path(): void
    {
        $routes = ControllerRouteBuilder::build(new AnnotatedActionsController());

        self::assertSame('/delete', self::routeOf($routes, 'delAction')[0]);
    }

    public function test_build_converts_method_annotation_to_method_option(): void
    {
        $routes = ControllerRouteBuilder::build(new AnnotatedActionsController());

        self::assertSame(['method' => 3], self::routeOf($routes, 'updateAction')[2]);
    }

    public function test_build_child_override_without_annotations_keeps_parent_annotations(): void
    {
        self::assertSame(
            [
                ['/page', 'pageAction', ['method' => 1]],
                ['/post', 'postAction', ['method' => 2]],
                ['/subpage', 'subpageAction', []],
            ],
            ControllerRouteBuilder::build(new ChildController()),
        );
    }

    public function test_build_of_controller_skips_run_action_and_has_action(): void
    {
        self::assertSame(
            [
                ['', 'indexAction', []],
                ['/add', 'addAction', []],
                ['/del', 'delAction', []],
                ['/json', 'jsonAction', []],
            ],
            ControllerRouteBuilder::build(new CRUDProductController()),
        );
    }

    public function test_build_of_restful_controller_returns_annotated_resource_routes(): void
    {
        self::assertSame(
            [
                ['', 'collectionAction', ['method' => 1]],
                ['', 'createAction', ['method' => 2]],
                ['/:id', 'updateAction', ['method' => 2]],
                ['/:id', 'loadAction', ['method' => 1]],
                ['/:id', 'deleteAction', ['method' => 4]],
            ],
            ControllerRouteBuilder::build(new ProductResourceController()),
        );
    }
}
