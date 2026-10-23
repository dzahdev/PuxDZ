<?php

declare(strict_types=1);

namespace Pux\Tests\Dispatch;

use PHPUnit\Framework\TestCase;
use Pux\Mux;

/**
 * Mounting with expansion (Mux::$expand = true, the default): submux routes are copied into
 * the parent under the mount path.
 */
final class MountTest extends TestCase
{
    public function test_dispatch_after_mount_of_static_routes_returns_routes_prefixed_with_mount_path(): void
    {
        $sub = new Mux();
        $sub->add('/page1', ['PageController', 'page1']);
        $sub->add('/page2', ['PageController', 'page2']);
        $mux = new Mux();

        $mux->mount('/sub', $sub);

        self::assertSame('/sub/page1', $mux->dispatch('/sub/page1')[1]);
        self::assertSame(['PageController', 'page2'], $mux->dispatch('/sub/page2')[2]);
    }

    public function test_mount_of_empty_mux_adds_no_routes(): void
    {
        $mux = new Mux();

        $mux->mount('/sub', new Mux());

        self::assertSame(0, $mux->length());
        self::assertNull($mux->dispatch('/sub'));
    }

    public function test_dispatch_after_mount_of_pcre_route_returns_route_with_prefixed_pattern(): void
    {
        $sub = new Mux();
        $sub->any('/hello/:name', ['HelloController', 'index']);
        $mux = new Mux();

        $mux->mount('/sub', $sub);
        $route = $mux->dispatch('/sub/hello/John');

        self::assertTrue($route[0]);
        self::assertSame('/sub/hello/:name', $route[3]['pattern']);
        self::assertSame('John', $route[3]['vars']['name']);
    }

    public function test_dispatch_after_mount_returns_null_for_paths_outside_mounted_routes(): void
    {
        $sub = new Mux();
        $sub->any('/hello/:name', ['HelloController', 'index']);
        $sub->any('/hello/test', ['HelloController', 'index']);
        $mux = new Mux();

        $mux->mount('/sub', $sub);

        self::assertNull($mux->dispatch('/sub/foo'));
        self::assertNull($mux->dispatch('/sub/hello'));
        self::assertNull($mux->dispatch('/foo'));
    }

    public function test_dispatch_after_mount_at_pcre_path_returns_static_sub_route_with_mount_variable(): void
    {
        $sub = new Mux();
        $sub->any('/hello/:name', ['HelloController', 'index']);
        $sub->any('/goodbye', ['GoodbyeController', 'index']);
        $mux = new Mux();

        $mux->mount('/sub/:country', $sub);
        $route = $mux->dispatch('/sub/UK/goodbye');

        self::assertSame(2, $mux->length());
        self::assertTrue($route[0]);
        self::assertSame('/sub/:country/goodbye', $route[3]['pattern']);
        self::assertSame('UK', $route[3]['vars']['country']);
    }

    public function test_dispatch_after_mount_at_pcre_path_returns_multi_segment_static_sub_route(): void
    {
        $sub = new Mux();
        $sub->any('/hello/there', ['HelloController', 'index']);
        $mux = new Mux();

        $mux->mount('/sub/:country', $sub);

        self::assertSame('/sub/:country/hello/there', $mux->dispatch('/sub/UK/hello/there')[3]['pattern']);
    }

    public function test_dispatch_after_mount_keeps_default_options_of_submux_routes(): void
    {
        $sub = new Mux();
        $sub->any('/foo', ['FooController', 'index'], ['default' => ['suffix' => 'csv']]);
        $sub->any('/hello/:name', ['HelloController', 'index'], ['default' => ['suffix' => 'json', 'prefix' => 'v1']]);
        $mux = new Mux();

        $mux->mount('/sub', $sub);
        $pcre = $mux->dispatch('/sub/hello/John');
        $static = $mux->dispatch('/sub/foo');

        self::assertSame(['suffix' => 'json', 'prefix' => 'v1'], $pcre[3]['default']);
        self::assertFalse($static[0]);
        self::assertSame('/sub/foo', $static[1]);
        self::assertSame(['suffix' => 'csv'], $static[3]['default']);
    }

    /**
     * @see docs/defect-registry.md R1
     */
    public function test_mount_of_closure_that_fills_given_mux_adds_its_routes(): void
    {
        $mux = new Mux();

        $mux->mount('/test', static function (Mux $sub): void {
            $sub->any('/hello/static', ['HelloController', 'show']);
            $sub->any('/hello/:name', ['HelloController', 'index']);
        });

        self::assertSame('/test/hello/static', $mux->dispatch('/test/hello/static')[1]);
        self::assertSame('/test/hello/:name', $mux->dispatch('/test/hello/John')[3]['pattern']);
    }

    /**
     * @see docs/defect-registry.md R1
     */
    public function test_mount_of_closure_that_returns_new_mux_adds_routes_of_returned_mux(): void
    {
        $mux = new Mux();

        $mux->mount('/bs/product', static function (Mux $ignored): Mux {
            $sub = new Mux();
            $sub->any('/create', ['Product', 'createAction']);
            $sub->any('/edit/:id', ['Product', 'editAction']);

            return $sub;
        });

        self::assertSame('/bs/product/create', $mux->dispatch('/bs/product/create')[1]);
        self::assertSame('/bs/product/edit/:id', $mux->dispatch('/bs/product/edit/30')[3]['pattern']);
    }
}
