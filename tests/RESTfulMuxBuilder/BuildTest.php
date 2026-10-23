<?php

declare(strict_types=1);

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

    /**
     * The default prefix is "/", and the resource path has one slash before the resource id.
     * Failed before the L39 fix: the path was "//product/:id", so "/product/10" matched no route.
     * The builder is created without a Mux, through the ?Mux parameter of P2: with the former
     * "Mux & $mux = null", PHP 8.4 and 8.5 report a deprecation when the class is loaded, and
     * the suite fails on it (failOnDeprecation).
     *
     * @see PLAN-port-php82.md L39
     * @see PLAN-port-php82.md P2
     */
    public function test_build_with_default_prefix_mounts_resource_at_root_with_single_slash(): void
    {
        $builder = new RESTfulMuxBuilder();
        $builder->addResource('product', new PlainResource());

        $route = $builder->build()->dispatch('/product/10');

        self::assertNotNull($route);
        self::assertSame('/product/:id', $route[3]['pattern']);
        self::assertSame('10', $route[3]['vars']['id']);
    }

    /**
     * A trailing slash of the prefix is dropped before "/<resource id>" is appended.
     * Failed before the L39 fix: the path was "/api//product/:id".
     *
     * @see PLAN-port-php82.md L39
     */
    public function test_build_with_prefix_ending_in_slash_mounts_resource_with_single_slash(): void
    {
        $builder = new RESTfulMuxBuilder(null, ['prefix' => '/api/']);
        $builder->addResource('product', new PlainResource());

        $route = $builder->build()->dispatch('/api/product/10');

        self::assertNotNull($route);
        self::assertSame('/api/product/:id', $route[3]['pattern']);
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
     * Failed before the L33 fix with 2 routes instead of 1: each build() call mounted every
     * registered resource again.
     *
     * @see PLAN-port-php82.md L33
     */
    public function test_build_called_twice_returns_same_mux_with_each_resource_route_once(): void
    {
        $mux = new Mux();
        $builder = new RESTfulMuxBuilder($mux, ['prefix' => '/=']);
        $builder->addResource('product', new PlainResource());
        $builder->build();

        $again = $builder->build();

        self::assertSame($mux, $again);
        self::assertSame(1, $again->length());
    }

    /**
     * A resource registered after build() would never be mounted: addResource() refuses it.
     *
     * @see PLAN-port-php82.md L33
     */
    public function test_add_resource_after_build_throws_logic_exception_naming_resource(): void
    {
        $mux = new Mux();
        $builder = new RESTfulMuxBuilder($mux, ['prefix' => '/=']);
        $builder->addResource('product', new PlainResource());
        $builder->build();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('order');

        $builder->addResource('order', new PlainResource());
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
