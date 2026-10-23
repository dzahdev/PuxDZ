<?php

declare(strict_types=1);

/*
 * Testing routes: RouteRequest::create() builds a request from a method, a path and a server
 * array, so a test dispatches without $_SERVER and without a web server. The same calls work
 * inside a PHPUnit test with assertSame() in place of check().
 *
 *     php eg/testing.php   prints "3 checks passed"
 */

namespace Pux\Examples;

use Pux\Mux;
use Pux\RouteExecutor;
use Pux\RouteRequest;

require __DIR__ . '/../vendor/autoload.php';

function routes(): Mux
{
    $mux = new Mux();
    $mux->post('/orders/:id/cancel', static fn (int $id): string => "order $id cancelled", [
        'require' => ['id' => '\d+'],
        'secure' => true,
    ]);

    return $mux;
}

function check(mixed $expected, mixed $actual, string $what): void
{
    if ($expected !== $actual) {
        $expected = var_export($expected, true);
        $actual = var_export($actual, true);

        throw new \UnexpectedValueException("$what: expected $expected, got $actual.");
    }
}

$https = ['HTTP_HOST' => 'shop.example.com', 'HTTPS' => 'on'];

$path = '/orders/12/cancel';

$route = routes()->dispatchRequest(RouteRequest::create('POST', $path, $https));
check('order 12 cancelled', $route === null ? null : RouteExecutor::execute($route), 'HTTPS');
check(null, routes()->dispatchRequest(RouteRequest::create('POST', $path)), 'HTTP');
check(null, routes()->dispatchRequest(RouteRequest::create('GET', $path, $https)), 'GET');

echo "3 checks passed\n";
