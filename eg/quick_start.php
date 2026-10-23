<?php

declare(strict_types=1);

/*
 * Quick start: routes for GET, POST and any method, dispatched for the current request and
 * executed. In a web server RouteRequest::fromServer() reads $_SERVER; on the command line the
 * example passes a server array built from the arguments.
 *
 *     php eg/quick_start.php                    GET /hello/world
 *     php eg/quick_start.php POST /orders       POST /orders
 */

namespace Pux\Examples;

use Pux\Mux;
use Pux\RouteExecutor;
use Pux\RouteRequest;

require __DIR__ . '/../vendor/autoload.php';

final class OrderController
{
    public function createAction(): string
    {
        return 'order created';
    }
}

$mux = new Mux();
$mux->get('/hello/:name', static fn (string $name): string => "hello $name");
$mux->post('/orders', [OrderController::class, 'createAction']);
$mux->any('/health', static fn (): string => 'ok');

$request = PHP_SAPI === 'cli'
    ? RouteRequest::fromServer([
        'REQUEST_METHOD' => $argv[1] ?? 'GET',
        'REQUEST_URI' => $argv[2] ?? '/hello/world',
    ])
    : RouteRequest::fromServer();

$route = $mux->dispatchRequest($request);
echo $route === null ? '404 Not Found' : RouteExecutor::execute($route), "\n";
