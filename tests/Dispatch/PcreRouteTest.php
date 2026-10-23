<?php

declare(strict_types=1);

namespace Pux\Tests\Dispatch;

use PHPUnit\Framework\TestCase;
use Pux\Mux;

/**
 * PCRE routes: a pattern with ':name' placeholders is compiled to a regular expression.
 */
final class PcreRouteTest extends TestCase
{
    public function test_dispatch_pcre_route_returns_route_with_source_pattern_and_matched_variable(): void
    {
        $mux = new Mux();
        $mux->add('/product/:id', ['ProductController', 'itemAction']);
        $mux->add('/product', ['ProductController', 'listAction']);

        $route = $mux->dispatch('/product/10');

        self::assertTrue($route[0]);
        self::assertSame('/product/:id', $route[3]['pattern']);
        self::assertSame(['ProductController', 'itemAction'], $route[2]);
        self::assertSame('10', $route[3]['vars']['id']);
    }

    public function test_dispatch_pcre_route_with_require_accepts_value_matching_requirement(): void
    {
        $mux = new Mux();
        $mux->add('/hello/:name', ['HelloController', 'index'], ['require' => ['name' => '\w+']]);

        self::assertSame('john', $mux->dispatch('/hello/john')[3]['vars']['name']);
    }

    public function test_dispatch_pcre_route_with_require_rejects_value_violating_requirement(): void
    {
        $mux = new Mux();
        $mux->add('/hello/:name', ['HelloController', 'index'], ['require' => ['name' => '\w+']]);

        self::assertNull($mux->dispatch('/hello/john-doe'));
    }

    /**
     * Upstream issue #109: a catch-all variable is written with a 'require' expression that
     * accepts "/"; there is no "+" modifier after a variable name.
     */
    public function test_dispatch_catch_all_variable_with_require_captures_remaining_segments(): void
    {
        $mux = new Mux();
        $mux->add('/search/:filters', ['ProductController', 'listAction'], ['require' => ['filters' => '.+']]);

        self::assertSame('a/b/c', $mux->dispatch('/search/a/b/c')[3]['vars']['filters']);
    }

    /**
     * Upstream issue #109: without 'require' a variable stops at "/".
     */
    public function test_dispatch_variable_without_require_rejects_path_with_more_segments(): void
    {
        $mux = new Mux();
        $mux->add('/search/:filters', ['ProductController', 'listAction']);

        self::assertNull($mux->dispatch('/search/a/b/c'));
    }
}
