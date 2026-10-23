<?php

namespace Pux\Tests\RESTfulMuxBuilder;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProductResourceController;
use Pux\Mux;
use Pux\MuxBuilder\RESTfulMuxBuilder;
use Pux\RouteRequest;
use Pux\Tests\Fixtures\PlainResource;

/**
 * RESTfulMuxBuilder: addResource() registers an Expandable resource, build() returns the Mux
 * with every resource mounted at "<prefix>/<resource id>". Checked through dispatch on the
 * built Mux.
 */
final class BuildTest extends TestCase
{
    public function test_build_mounts_resource_routes_under_prefix_and_resource_id(): void
    {
        $mux = new Mux();
        $builder = new RESTfulMuxBuilder($mux, ['prefix' => '/=']);
        $builder->addResource('product', new PlainResource());

        $route = $builder->build()->dispatch('/=/product/10');

        self::assertSame('/=/product/:id', $route[3]['pattern']);
        self::assertSame([PlainResource::class, 'loadAction'], $route[2]);
        self::assertSame('10', $route[3]['vars']['id']);
    }

    public function test_build_returns_mux_given_to_constructor(): void
    {
        $mux = new Mux();
        $builder = new RESTfulMuxBuilder($mux, ['prefix' => '/=']);
        $builder->addResource('product', new PlainResource());

        self::assertSame($mux, $builder->build());
    }

    /**
     * Failed before the L14 fix with 2 routes instead of 1: addResource() mounted the resource
     * and build() mounted it again.
     *
     * @see PLAN-port-php82.md L14
     */
    public function test_build_after_add_resource_mounts_each_resource_route_once(): void
    {
        $mux = new Mux();
        $builder = new RESTfulMuxBuilder($mux, ['prefix' => '/=']);
        $builder->addResource('product', new PlainResource());

        self::assertSame(1, $builder->build()->length());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function resourceRequests(): array
    {
        return [
            'GET' => ['GET', 'loadAction'],
            'DELETE' => ['DELETE', 'deleteAction'],
        ];
    }

    /**
     * Failed before the L4 fix: dispatch() did not pass the request to match(), which read the
     * method from the server superglobal.
     *
     * @see PLAN-port-php82.md L4
     */
    #[DataProvider('resourceRequests')]
    public function test_build_with_restful_controller_selects_action_by_request_method(string $method, string $action): void
    {
        $mux = new Mux();
        $builder = new RESTfulMuxBuilder($mux, ['prefix' => '/=']);
        $builder->addResource('product', new ProductResourceController());

        $route = $builder->build()->dispatch('/=/product/10', new RouteRequest($method, '/=/product/10'));

        self::assertSame(['ProductResourceController', $action], $route[2]);
    }
}
