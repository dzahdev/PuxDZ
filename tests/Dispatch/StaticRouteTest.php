<?php

declare(strict_types=1);

namespace Pux\Tests\Dispatch;

use PHPUnit\Framework\TestCase;
use Pux\Mux;

/**
 * Static routes: a pattern without ':' is matched by string equality.
 */
final class StaticRouteTest extends TestCase
{
    public function test_dispatch_static_route_returns_route_with_its_path_callback_and_empty_options(): void
    {
        $mux = new Mux();
        $mux->add('/product', ['ProductController', 'listAction']);

        self::assertSame(
            [false, '/product', ['ProductController', 'listAction'], []],
            $mux->dispatch('/product'),
        );
    }

    public function test_dispatch_root_path_returns_root_route(): void
    {
        $mux = new Mux();
        $mux->add('/', ['HelloController', 'index']);

        self::assertSame([false, '/', ['HelloController', 'index'], []], $mux->dispatch('/'));
    }

    public function test_dispatch_unknown_path_returns_null(): void
    {
        $mux = new Mux();
        $mux->add('/product', ['ProductController', 'listAction']);

        self::assertNull($mux->dispatch('/products'));
    }

    public function test_add_registers_one_route_per_call(): void
    {
        $mux = new Mux();
        $mux->add('/product/:id', ['ProductController', 'itemAction']);
        $mux->any('/product', ['ProductController', 'listAction']);

        self::assertSame(2, $mux->length());
    }

    public function test_dispatch_on_mux_given_routes_by_set_routes_returns_those_routes(): void
    {
        $source = new Mux();
        $source->add('/product', ['ProductController', 'listAction']);
        $target = new Mux();

        $target->setRoutes($source->getRoutes());

        self::assertSame(
            [false, '/product', ['ProductController', 'listAction'], []],
            $target->dispatch('/product'),
        );
    }

    /**
     * Mux is an IteratorAggregate; getIterator() declares its return type since the P3 fix
     * (before: #[\ReturnTypeWillChange] without a type, the same iteration).
     *
     * @see docs/defect-registry.md P3
     */
    public function test_iterating_mux_yields_route_paths_in_insertion_order(): void
    {
        $mux = new Mux();
        $mux->add('/foo', 'foo');
        $mux->add('/bar', 'bar');

        $paths = [];
        foreach ($mux as $route) {
            $paths[] = $route[1];
        }

        self::assertSame(['/foo', '/bar'], $paths);
    }
}
