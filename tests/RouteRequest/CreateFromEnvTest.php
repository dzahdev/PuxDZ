<?php

declare(strict_types=1);

namespace Pux\Tests\RouteRequest;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pux\RouteRequest;

/**
 * RouteRequest::createFromEnv and RouteRequest::create read an environment array: either a flat
 * server array or a globals-shaped array with the keys _SERVER, _GET, _POST, _REQUEST, _COOKIE
 * and _SESSION.
 *
 * Expected values were checked against RouteRequest on top of its former third-party parent
 * class before Stage 1 rewrote RouteRequest without a parent.
 */
final class CreateFromEnvTest extends TestCase
{
    public function test_create_from_env_reads_nested_server_array_and_superglobals(): void
    {
        $request = RouteRequest::createFromEnv([
            '_SERVER' => ['REQUEST_METHOD' => 'POST', 'PATH_INFO' => '/orders', 'HTTP_HOST' => 'shop.test'],
            '_REQUEST' => ['a' => '1'],
            '_GET' => ['q' => 'x'],
            '_POST' => ['b' => '2'],
            '_COOKIE' => ['sid' => 's'],
            '_SESSION' => ['user' => 'u'],
        ]);

        self::assertSame('POST', $request->getRequestMethod());
        self::assertSame('/orders', $request->getPath());
        self::assertTrue($request->hostEqual('shop.test'));
        self::assertSame(['a' => '1'], $request->parameters);
        self::assertSame(['q' => 'x'], $request->queryParameters);
        self::assertSame(['b' => '2'], $request->bodyParameters);
        self::assertSame(['sid' => 's'], $request->cookieParameters);
        self::assertSame(['user' => 'u'], $request->sessionParameters);
    }

    public function test_create_from_env_prefers_path_info_to_request_uri(): void
    {
        $request = RouteRequest::createFromEnv([
            'REQUEST_METHOD' => 'GET',
            'PATH_INFO' => '/info',
            'REQUEST_URI' => '/uri',
            '_REQUEST' => [], '_GET' => [], '_POST' => [], '_COOKIE' => [], '_SESSION' => [],
        ]);

        self::assertSame('/info', $request->getPath());
    }

    public function test_create_keeps_flat_environment_as_server_parameters(): void
    {
        $request = RouteRequest::create('GET', '/', ['HTTP_HOST' => 'example.com', 'SERVER_PORT' => '443']);

        self::assertSame(['HTTP_HOST' => 'example.com', 'SERVER_PORT' => '443'], $request->serverParameters);
        self::assertSame([], $request->queryParameters);
    }

    public function test_create_derives_headers_from_http_and_content_keys(): void
    {
        $request = RouteRequest::create('POST', '/', [
            'HTTP_HOST' => 'example.com',
            'HTTP_X_REQUEST_ID' => '7',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => '2',
            'SERVER_PORT' => '80',
        ]);

        self::assertSame([
            'Host' => 'example.com',
            'X-Request-Id' => '7',
            'Content-Type' => 'application/json',
            'Content-Length' => '2',
        ], $request->headers);
    }

    /**
     * Upstream issue #103: the query string is not part of the path; it is read from
     * QUERY_STRING through queryStringMatch. Failed before the L32 fix: the path was
     * "/tools?p=K94rF" (createFromEnv did not strip the query string, fromServer did).
     *
     * @see docs/defect-registry.md L32
     */
    public function test_create_from_env_takes_path_of_request_uri_without_query_string(): void
    {
        $request = RouteRequest::createFromEnv([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/tools?p=K94rF',
            'QUERY_STRING' => 'p=K94rF',
        ]);

        $matches = [];

        self::assertSame('/tools', $request->getPath());
        self::assertTrue($request->queryStringMatch('#p=(\w+)#', $matches));
        self::assertSame('K94rF', $matches[1]);
    }

    /**
     * The former code fell back to the path "/" with the comment "check path or throw
     * exception"; no test specified that fallback.
     */
    public function test_create_from_env_without_path_info_and_request_uri_throws_exception_naming_them(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PATH_INFO or REQUEST_URI');

        RouteRequest::createFromEnv([
            'REQUEST_METHOD' => 'GET',
            '_REQUEST' => [], '_GET' => [], '_POST' => [], '_COOKIE' => [], '_SESSION' => [],
        ]);
    }
}
