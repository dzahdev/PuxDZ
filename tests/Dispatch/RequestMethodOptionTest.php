<?php

namespace Pux\Tests\Dispatch;

use PHPUnit\Framework\TestCase;
use Pux\Mux;
use Pux\RequestMethod;
use Pux\RouteRequest;

/**
 * The 'method' route option accepts a Pux\RequestMethod case, a method name in any case or the
 * integer value of a case (the deprecated REQUEST_METHOD_* constants hold these values).
 *
 * @see PLAN-port-php82.md L23
 */
final class RequestMethodOptionTest extends TestCase
{
    public function test_dispatch_route_with_request_method_case_matches_only_request_with_that_method(): void
    {
        $mux = new Mux();
        $mux->add('/p', ['PageController', 'page1'], ['method' => RequestMethod::POST]);

        self::assertSame('/p', $mux->dispatch('/p', new RouteRequest('POST', '/p'))[1]);
        self::assertNull($mux->dispatch('/p', new RouteRequest('GET', '/p')));
    }

    public function test_dispatch_route_with_lower_case_method_name_matches_request_with_that_method(): void
    {
        $mux = new Mux();
        $mux->add('/p', ['PageController', 'page1'], ['method' => 'post']);

        self::assertSame('/p', $mux->dispatch('/p', new RouteRequest('POST', '/p'))[1]);
    }

    public function test_dispatch_route_with_deprecated_constant_matches_request_with_that_method(): void
    {
        $mux = new Mux();
        $mux->add('/p', ['PageController', 'page1'], ['method' => REQUEST_METHOD_PUT]);

        self::assertSame('/p', $mux->dispatch('/p', new RouteRequest('PUT', '/p'))[1]);
    }

    public function test_dispatch_request_with_method_outside_enum_skips_method_constrained_route(): void
    {
        $mux = new Mux();
        $mux->get('/x', ['PageController', 'page1']);
        $mux->any('/x', ['PageController', 'page2']);

        self::assertSame(['PageController', 'page2'], $mux->dispatch('/x', new RouteRequest('PROPFIND', '/x'))[2]);
    }

    public function test_add_with_unknown_method_name_throws_value_error_naming_it(): void
    {
        $mux = new Mux();

        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('FETCH');

        $mux->add('/p', ['PageController', 'page1'], ['method' => 'FETCH']);
    }

    public function test_add_with_unknown_method_number_throws_value_error(): void
    {
        $mux = new Mux();

        $this->expectException(\ValueError::class);

        $mux->add('/p', ['PageController', 'page1'], ['method' => 99]);
    }
}
