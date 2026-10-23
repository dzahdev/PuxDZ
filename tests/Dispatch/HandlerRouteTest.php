<?php

declare(strict_types=1);

namespace Pux\Tests\Dispatch;

use PHPUnit\Framework\TestCase;
use Pux\Mux;
use Pux\Tests\Fixtures\EchoHandler;

/**
 * A static route whose callback is a Pux\Handler object is matched by path prefix, like a
 * submux mount point (see "Static route" in GLOSSARY.md). Pux\Handler replaces the removed
 * third-party interface that Mux::match checked before Stage 1.
 */
final class HandlerRouteTest extends TestCase
{
    public function test_dispatch_of_static_route_with_handler_callback_matches_path_prefix(): void
    {
        $mux = new Mux();
        $handler = new EchoHandler();
        $mux->add('/app', $handler);

        $route = $mux->dispatch('/app/users/7');

        self::assertSame('/app', $route[1]);
        self::assertSame($handler, $route[2]);
    }

    public function test_dispatch_of_static_route_with_non_handler_object_requires_exact_path(): void
    {
        $mux = new Mux();
        $mux->add('/app', new \stdClass());

        self::assertNull($mux->dispatch('/app/users/7'));
    }
}
