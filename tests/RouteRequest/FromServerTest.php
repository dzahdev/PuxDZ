<?php

namespace Pux\Tests\RouteRequest;

use PHPUnit\Framework\TestCase;
use Pux\RouteRequest;

/**
 * RouteRequest::fromServer builds a request from an array of the shape of the server
 * superglobal. It is the only place in the library that reads the superglobal, and only when no
 * array is given (architectural invariant 7 of the port plan).
 */
final class FromServerTest extends TestCase
{
    public function test_from_server_reads_method_path_and_server_parameters(): void
    {
        $server = ['REQUEST_METHOD' => 'POST', 'PATH_INFO' => '/orders', 'HTTP_HOST' => 'shop.test'];

        $request = RouteRequest::fromServer($server);

        self::assertSame('POST', $request->getRequestMethod());
        self::assertSame('/orders', $request->getPath());
        self::assertSame($server, $request->serverParameters);
        self::assertTrue($request->hostEqual('shop.test'));
    }

    public function test_from_server_without_path_info_takes_path_of_request_uri_without_query_string(): void
    {
        $request = RouteRequest::fromServer(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/search?q=x&page=2']);

        self::assertSame('/search', $request->getPath());
    }

    public function test_from_server_derives_headers_from_http_and_content_keys(): void
    {
        $request = RouteRequest::fromServer([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/',
            'HTTP_USER_AGENT' => 'curl/8',
            'HTTP_X_REQUEST_ID' => '7',
            'CONTENT_TYPE' => 'text/plain',
        ]);

        self::assertSame(['User-Agent' => 'curl/8', 'X-Request-Id' => '7', 'Content-Type' => 'text/plain'], $request->headers);
    }

    public function test_from_server_leaves_request_parameter_arrays_empty(): void
    {
        $request = RouteRequest::fromServer(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/?q=x']);

        self::assertSame([], $request->parameters);
        self::assertSame([], $request->queryParameters);
        self::assertSame([], $request->bodyParameters);
        self::assertSame([], $request->cookieParameters);
        self::assertSame([], $request->sessionParameters);
    }

    public function test_from_server_without_request_method_throws_exception_naming_it(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('REQUEST_METHOD');

        RouteRequest::fromServer(['REQUEST_URI' => '/']);
    }

    public function test_from_server_without_path_info_and_request_uri_throws_exception_naming_them(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PATH_INFO or REQUEST_URI');

        RouteRequest::fromServer(['REQUEST_METHOD' => 'GET']);
    }

    /**
     * PHPUnit runs in the CLI, where the server superglobal has no REQUEST_METHOD: the exception
     * shows that the superglobal was read without the test writing it.
     */
    public function test_from_server_without_argument_reads_server_superglobal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('REQUEST_METHOD');

        RouteRequest::fromServer();
    }
}
