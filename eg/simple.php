<?php

declare(strict_types=1);

/*
 * PCRE routes with a 'require' expression and a 'default' value; the route variable is
 * converted to the int parameter of the action.
 *
 *     php eg/simple.php   prints "product list", "product 7" and "product 1"
 */

namespace Pux\Examples;

use Pux\Mux;
use Pux\RouteExecutor;
use Pux\RouteRequest;

require __DIR__ . '/../vendor/autoload.php';

final class ProductController
{
    public function listAction(): string
    {
        return 'product list';
    }

    public function itemAction(int $id): string
    {
        return "product $id";
    }
}

$mux = new Mux();
$mux->get('/product', [ProductController::class, 'listAction']);
$mux->get('/product/item(/:id)', [ProductController::class, 'itemAction'], [
    'require' => ['id' => '\d+'],
    'default' => ['id' => '1'],
]);

foreach (['/product', '/product/item/7', '/product/item'] as $path) {
    $route = $mux->dispatch($path, new RouteRequest('GET', $path));
    if ($route === null) {
        throw new \UnexpectedValueException("No route for $path.");
    }
    echo RouteExecutor::execute($route), "\n";
}
