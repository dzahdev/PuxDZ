<?php

namespace Pux\Tests\Dispatch;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pux\Mux;

/**
 * Mounting without expansion (Mux::$expand = false): the parent keeps one route per mount
 * point and dispatches the rest of the path to the submux.
 */
final class MountWithoutExpandTest extends TestCase
{
    private function muxWithoutExpand(): Mux
    {
        $mux = new Mux();
        $mux->expand = false;

        return $mux;
    }

    public function test_dispatch_with_expand_disabled_returns_submux_pcre_route_for_rest_of_path(): void
    {
        $sub = new Mux();
        $sub->any('/hello/:name', ['HelloController', 'index']);
        $sub->any('/foo', ['HelloController', 'show']);
        $mux = $this->muxWithoutExpand();

        $mux->mount('/sub', $sub);
        $route = $mux->dispatch('/sub/hello/John');

        self::assertTrue($route[0]);
        self::assertSame('/hello/:name', $route[3]['pattern']);
        self::assertSame('John', $route[3]['vars']['name']);
    }

    public function test_dispatch_with_expand_disabled_returns_submux_static_route_for_rest_of_path(): void
    {
        $sub = new Mux();
        $sub->any('/hello/:name', ['HelloController', 'index']);
        $sub->any('/foo', ['HelloController', 'show']);
        $mux = $this->muxWithoutExpand();

        $mux->mount('/sub', $sub);

        self::assertSame([false, '/foo', ['HelloController', 'show'], []], $mux->dispatch('/sub/foo'));
    }

    public function test_mount_with_expand_disabled_adds_one_route_per_mount_point(): void
    {
        $pages = new Mux();
        $pages->add('/page1', ['PageController', 'page1']);
        $pages->add('/page2', ['PageController', 'page2']);
        $others = new Mux();
        $others->add('/bar', ['PageController', 'page1']);
        $others->add('/zoo', ['PageController', 'page2']);
        $mux = $this->muxWithoutExpand();

        $mux->mount('/sub', $pages);
        $mux->mount('/foo', $others);

        self::assertSame(2, $mux->length());
        self::assertSame('/page1', $mux->dispatch('/sub/page1')[1]);
        self::assertSame('/page2', $mux->dispatch('/sub/page2')[1]);
        self::assertSame('/bar', $mux->dispatch('/foo/bar')[1]);
        self::assertSame('/zoo', $mux->dispatch('/foo/zoo')[1]);
    }

    /**
     * @see PLAN-port-php82.md R1
     */
    public function test_mount_of_closure_with_expand_disabled_dispatches_to_routes_it_added(): void
    {
        $mux = $this->muxWithoutExpand();

        $mux->mount('/test', static function (Mux $sub): void {
            $sub->any('/hello/static', ['HelloController', 'show']);
            $sub->any('/hello/:name', ['HelloController', 'index']);
        });

        self::assertSame('/hello/:name', $mux->dispatch('/test/hello/John')[3]['pattern']);
        self::assertSame('/hello/static', $mux->dispatch('/test/hello/static')[1]);
    }

    /**
     * @see PLAN-port-php82.md L13
     */
    #[Group('known-defect')]
    public function test_dispatch_with_expand_disabled_and_pcre_mount_point_keeps_mount_variable(): void
    {
        $sub = new Mux();
        $sub->any('/item/:id', ['ProductController', 'itemAction']);
        $mux = $this->muxWithoutExpand();

        $mux->mount('/u/:user', $sub);
        $route = $mux->dispatch('/u/bob/item/7');

        self::assertSame('7', $route[3]['vars']['id']);
        self::assertArrayHasKey('user', $route[3]['vars']);
        self::assertSame('bob', $route[3]['vars']['user']);
    }
}
