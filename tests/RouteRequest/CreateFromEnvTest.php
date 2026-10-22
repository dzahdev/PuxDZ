<?php

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
}
