<?php

declare(strict_types=1);

namespace Pux\Tests\Dispatch;

use PHPUnit\Framework\TestCase;
use Pux\Exception\PatternSyntax;
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

    /**
     * An optional group that is not in the path leaves its variable out of 'vars', also when a
     * required variable follows the group. Failed before the L38 fix: preg_match() reported the
     * group as the empty string, so 'vars' held 'b' => ''.
     *
     * @see docs/defect-registry.md L38
     */
    public function test_dispatch_with_omitted_optional_group_before_variable_leaves_its_variable_out_of_vars(): void
    {
        $mux = new Mux();
        $mux->add('/a(/:b)/:c', ['ProductController', 'itemAction'], ['default' => ['b' => 'x']]);

        $vars = $mux->dispatch('/a/1')[3]['vars'];

        self::assertArrayNotHasKey('b', $vars);
        self::assertSame('1', $vars['c']);
    }

    /**
     * Failed before the L37 fix: preg_match() returned false on the backtrack limit and the
     * route was skipped, so dispatch() returned null as for a path that matches no route.
     * pcre.jit is turned off because the JIT does not apply pcre.backtrack_limit.
     *
     * @see docs/defect-registry.md L37
     */
    public function test_dispatch_pcre_route_failing_at_match_time_throws_pattern_syntax(): void
    {
        $mux = new Mux();
        $mux->add('/:id', ['ProductController', 'itemAction'], ['require' => ['id' => '(a+)+$']]);
        $backtrackLimit = ini_get('pcre.backtrack_limit');
        $jit = ini_get('pcre.jit');
        self::assertIsString($backtrackLimit);
        self::assertIsString($jit);
        ini_set('pcre.backtrack_limit', '100');
        ini_set('pcre.jit', '0');

        try {
            $this->expectException(PatternSyntax::class);
            $this->expectExceptionMessage('Backtrack limit exhausted');

            $mux->dispatch('/' . str_repeat('a', 30) . 'b');
        } finally {
            ini_set('pcre.backtrack_limit', $backtrackLimit);
            ini_set('pcre.jit', $jit);
        }
    }
}
