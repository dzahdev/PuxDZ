<?php

namespace Pux\Tests\RouteExecutor;

use PHPUnit\Framework\TestCase;
use Pux\Tests\Fixtures\RequestEchoController;

/**
 * Inside an action called through Controller::call, Controller::getRequest returns a
 * Pux\RouteRequest built from the environment by RouteRequest::createFromEnv: a flat
 * environment of the shape of $_SERVER and one with the server array under _SERVER both work.
 */
final class ControllerRequestTest extends TestCase
{
    public function test_call_gives_action_request_built_from_flat_environment(): void
    {
        $controller = new RequestEchoController();
        $environment = [
            'REQUEST_METHOD' => 'POST',
            'PATH_INFO' => '/orders',
            'pux.controller_action' => 'requestAction',
            'pux.route' => [false, '/orders', [$controller, 'requestAction'], []],
        ];

        self::assertSame(['POST', '/orders'], $controller->call($environment, []));
    }

    public function test_call_gives_action_request_built_from_server_array_of_environment(): void
    {
        $controller = new RequestEchoController();
        $environment = [
            '_SERVER' => ['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/orders/7'],
            'pux.controller_action' => 'requestAction',
            'pux.route' => [false, '/orders/7', [$controller, 'requestAction'], []],
        ];

        self::assertSame(['PUT', '/orders/7'], $controller->call($environment, []));
    }
}
