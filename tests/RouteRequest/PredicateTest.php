<?php

declare(strict_types=1);

namespace Pux\Tests\RouteRequest;

use PHPUnit\Framework\TestCase;
use Pux\RouteRequest;

/**
 * RouteRequest factories and predicates.
 */
final class PredicateTest extends TestCase
{
    public function test_create_keeps_request_method_and_path(): void
    {
        $request = RouteRequest::create('GET', '/foo/bar');

        self::assertSame('GET', $request->getRequestMethod());
        self::assertSame('/foo/bar', $request->getPath());
    }

    public function test_path_like_with_leading_segment_of_path_returns_true(): void
    {
        self::assertTrue(RouteRequest::create('GET', '/foo/bar')->pathLike('/foo'));
    }

    /**
     * @see docs/defect-registry.md L1
     */
    public function test_path_like_with_text_absent_from_path_returns_false(): void
    {
        self::assertFalse(RouteRequest::create('GET', '/foo', ['HTTP_HOST' => 'example.com'])->pathLike('zzz'));
    }

    /**
     * @see docs/defect-registry.md L1
     */
    public function test_path_match_with_non_matching_regex_returns_false(): void
    {
        self::assertFalse(RouteRequest::create('GET', '/foo', ['HTTP_HOST' => 'example.com'])->pathMatch('#^/bar$#'));
    }

    /**
     * @see docs/defect-registry.md L1
     */
    public function test_host_match_with_non_matching_regex_returns_false(): void
    {
        self::assertFalse(RouteRequest::create('GET', '/foo', ['HTTP_HOST' => 'example.com'])->hostMatch('#^nothing$#'));
    }

    public function test_request_method_equal_accepts_own_method_and_rejects_other(): void
    {
        $request = RouteRequest::create('GET', '/foo/bar');

        self::assertTrue($request->requestMethodEqual('GET'));
        self::assertFalse($request->requestMethodEqual('POST'));
    }

    public function test_path_contain_finds_each_segment_of_path(): void
    {
        $request = RouteRequest::create('GET', '/foo/bar');

        self::assertTrue($request->pathContain('/foo'));
        self::assertTrue($request->pathContain('/bar'));
    }

    public function test_path_end_with_accepts_actual_suffix_only(): void
    {
        $request = RouteRequest::create('GET', '/foo/bar.json');

        self::assertTrue($request->pathEndWith('.json'));
        self::assertFalse($request->pathEndWith('.js'));
        self::assertFalse($request->pathEndWith('.xml'));
    }

    public function test_match_constraints_with_matching_second_alternative_returns_true(): void
    {
        $request = RouteRequest::create('GET', '/foo', ['HTTP_HOST' => 'example.com']);

        self::assertTrue($request->matchConstraints([['path' => '/nope'], ['path' => '/foo']]));
    }

    /**
     * @see docs/defect-registry.md L2
     */
    public function test_match_constraints_with_only_non_matching_alternative_returns_false(): void
    {
        $request = RouteRequest::create('GET', '/foo', ['HTTP_HOST' => 'example.com']);

        self::assertFalse($request->matchConstraints([['path' => '/nope']]));
    }

    /**
     * Failed before the L12 fix with 5 warnings "Undefined array key" for _REQUEST, _GET, _POST,
     * _COOKIE and _SESSION.
     *
     * @see docs/defect-registry.md L12
     */
    public function test_create_from_env_without_superglobal_keys_reads_path_and_method(): void
    {
        $request = RouteRequest::createFromEnv(['REQUEST_METHOD' => 'GET', 'PATH_INFO' => '/z']);

        self::assertSame('/z', $request->getPath());
        self::assertSame('GET', $request->getRequestMethod());
    }
}
