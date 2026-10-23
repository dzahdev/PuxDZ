<?php

declare(strict_types=1);

namespace Pux\Tests\Dispatch;

use PHPUnit\Framework\TestCase;
use Pux\Mux;

/**
 * Dispatch returns the first matching route; Mux::sort() reorders routes so that the more
 * specific one is tried first.
 */
final class RouteOrderTest extends TestCase
{
    private function overlappingPcreRoutes(): Mux
    {
        $mux = new Mux();
        $mux->add('/p/:rest', 'catch-all', ['require' => ['rest' => '.+']]);
        $mux->add('/p/:id/edit', 'edit');

        return $mux;
    }

    public function test_dispatch_without_sort_returns_first_added_of_overlapping_routes(): void
    {
        $mux = $this->overlappingPcreRoutes();

        self::assertSame('catch-all', $mux->dispatch('/p/1/edit')[2]);
    }

    public function test_dispatch_after_sort_returns_longer_of_overlapping_pcre_routes(): void
    {
        $mux = $this->overlappingPcreRoutes();

        $mux->sort();

        self::assertSame('edit', $mux->dispatch('/p/1/edit')[2]);
    }

    public function test_dispatch_after_sort_with_expand_disabled_delegates_to_longer_mount_prefix(): void
    {
        $short = new Mux();
        $short->add('/deep/x', 'short-mount');
        $long = new Mux();
        $long->add('/x', 'long-mount');
        $mux = new Mux();
        $mux->expand = false;
        $mux->mount('/sub', $short);
        $mux->mount('/sub/deep', $long);

        $mux->sort();

        self::assertSame('long-mount', $mux->dispatch('/sub/deep/x')[2]);
    }
}
