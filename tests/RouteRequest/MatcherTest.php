<?php

namespace Pux\Tests\RouteRequest;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pux\RouteRequest;

/**
 * RouteRequest predicates that PredicateTest does not cover: path equality and prefix, the
 * matching case of the regex predicates, host, port and query string. Together with
 * PredicateTest every public predicate of RouteRequest has a test (Stage 1 acceptance).
 *
 * Expected values were checked against RouteRequest on top of its former third-party parent
 * class before Stage 1 rewrote RouteRequest without a parent.
 */
final class MatcherTest extends TestCase
{
    public function test_path_equal_ignores_case_and_rejects_other_path(): void
    {
        $request = RouteRequest::create('GET', '/Foo/Bar');

        self::assertTrue($request->pathEqual('/foo/bar'));
        self::assertFalse($request->pathEqual('/foo'));
    }

    public function test_path_start_with_accepts_leading_part_only(): void
    {
        $request = RouteRequest::create('GET', '/foo/bar');

        self::assertTrue($request->pathStartWith('/foo'));
        self::assertFalse($request->pathStartWith('/bar'));
    }

    public function test_path_match_with_matching_regex_returns_true_and_fills_captures(): void
    {
        $matches = [];

        self::assertTrue(RouteRequest::create('GET', '/item/42')->pathMatch('#^/item/(\d+)$#', $matches));
        self::assertSame(['/item/42', '42'], $matches);
    }

    public function test_host_match_with_matching_regex_returns_true(): void
    {
        self::assertTrue(RouteRequest::create('GET', '/', ['HTTP_HOST' => 'api.example.com'])->hostMatch('#^api\.#'));
    }

    public function test_host_equal_ignores_case_and_rejects_other_host(): void
    {
        $request = RouteRequest::create('GET', '/', ['HTTP_HOST' => 'Example.com']);

        self::assertTrue($request->hostEqual('example.com'));
        self::assertFalse($request->hostEqual('github.com'));
    }

    public function test_host_predicates_without_host_return_false(): void
    {
        $request = RouteRequest::create('GET', '/');

        self::assertFalse($request->hostEqual('example.com'));
        self::assertFalse($request->hostMatch('#.*#'));
    }

    public function test_port_equal_compares_server_port_as_integer(): void
    {
        $request = RouteRequest::create('GET', '/', ['SERVER_PORT' => '8080']);

        self::assertTrue($request->portEqual(8080));
        self::assertFalse($request->portEqual('80'));
    }

    public function test_query_string_match_with_matching_regex_returns_true(): void
    {
        self::assertTrue(RouteRequest::create('GET', '/', ['QUERY_STRING' => 'page=2'])->queryStringMatch('#page=\d#'));
    }

    /**
     * @see PLAN-port-php82.md L1
     */
    public function test_query_string_match_with_non_matching_regex_returns_false(): void
    {
        self::assertFalse(RouteRequest::create('GET', '/', ['QUERY_STRING' => 'page=2'])->queryStringMatch('#^sort=#'));
    }
}
