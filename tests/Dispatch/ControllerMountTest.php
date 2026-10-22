<?php

namespace Pux\Tests\Dispatch;

use CRUDProductController;
use ExpandableProductController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ProductResourceController;
use Pux\Mux;
use Pux\RouteRequest;

/**
 * Mounting controllers: an ExpandableController (or its expansion) mounted on a path
 * dispatches to its action routes.
 */
final class ControllerMountTest extends TestCase
{
    public function test_dispatch_after_mount_of_controller_expansion_returns_static_action_routes(): void
    {
        $mux = new Mux();

        $mux->mount('/product', (new CRUDProductController())->expand());

        self::assertSame([false, '/product'], array_slice($mux->dispatch('/product'), 0, 2));
        self::assertSame([false, '/product/add'], array_slice($mux->dispatch('/product/add'), 0, 2));
        self::assertSame([false, '/product/del'], array_slice($mux->dispatch('/product/del'), 0, 2));
    }

    public function test_dispatch_after_mount_of_controller_object_returns_class_name_action_callbacks(): void
    {
        $mux = new Mux();

        $mux->mount('/product', new CRUDProductController());
        $mux->any('/', ['ProductController', 'indexAction']);

        self::assertSame(['CRUDProductController', 'indexAction'], $mux->dispatch('/product')[2]);
        self::assertSame(['CRUDProductController', 'addAction'], $mux->dispatch('/product/add')[2]);
        self::assertSame(['CRUDProductController', 'delAction'], $mux->dispatch('/product/del')[2]);
        self::assertNull($mux->dispatch('/foo'));
        self::assertNull($mux->dispatch('/bar'));
    }

    public function test_dispatch_after_mount_of_annotated_controller_returns_routes_without_method_annotation(): void
    {
        $mux = new Mux();

        $mux->mount('/product', (new ExpandableProductController())->expand());

        self::assertSame(['ExpandableProductController', 'fooBarAction'], $mux->dispatch('/product/foo/bar')[2]);
        self::assertSame(['ExpandableProductController', 'indexAction'], $mux->dispatch('/product')[2]);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function annotatedActionRequests(): array
    {
        return [
            'DELETE /product/delete' => ['DELETE', '/product/delete', 'delAction'],
            'PUT /product/update' => ['PUT', '/product/update', 'updateAction'],
            'POST /product/add' => ['POST', '/product/add', 'addAction'],
            'GET /product/item' => ['GET', '/product/item', 'itemAction'],
        ];
    }

    /**
     * @see PLAN-port-php82.md L4
     */
    #[Group('known-defect')]
    #[DataProvider('annotatedActionRequests')]
    public function test_dispatch_after_mount_of_annotated_controller_honours_method_annotation(
        string $method,
        string $path,
        string $action,
    ): void {
        $mux = new Mux();
        $mux->mount('/product', (new ExpandableProductController())->expand());

        $route = $mux->dispatch($path, new RouteRequest($method, $path));

        self::assertSame(['ExpandableProductController', $action], $route[2]);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function resourceRequests(): array
    {
        return [
            'GET /product/10' => ['GET', '/product/10', 'loadAction'],
            'DELETE /product/10' => ['DELETE', '/product/10', 'deleteAction'],
            'POST /product' => ['POST', '/product', 'createAction'],
            'POST /product/10' => ['POST', '/product/10', 'updateAction'],
        ];
    }

    /**
     * @see PLAN-port-php82.md L4
     */
    #[Group('known-defect')]
    #[DataProvider('resourceRequests')]
    public function test_dispatch_after_mount_of_restful_controller_selects_action_by_method(
        string $method,
        string $path,
        string $action,
    ): void {
        $mux = new Mux();
        $mux->mount('/product', (new ProductResourceController())->expand());

        $route = $mux->dispatch($path, new RouteRequest($method, $path));

        self::assertSame(['ProductResourceController', $action], $route[2]);
    }
}
