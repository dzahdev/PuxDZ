<?php

declare(strict_types=1);

/*
 * The smallest router: one static route, dispatched and executed.
 *
 *     php eg/hello.php           prints "hello"
 *     php eg/hello.php /missing  prints "404 /missing"
 */

namespace Pux\Examples;

use Pux\Mux;
use Pux\RouteExecutor;
use Pux\RouteRequest;

require __DIR__ . '/../vendor/autoload.php';

final class HelloController
{
    public function helloAction(): string
    {
        return 'hello';
    }
}

$path = $argv[1] ?? '/hello';

$mux = new Mux();
$mux->get('/hello', [HelloController::class, 'helloAction']);

$route = $mux->dispatch($path, new RouteRequest('GET', $path));
echo $route === null ? "404 $path" : RouteExecutor::execute($route), "\n";
