<?php

declare(strict_types=1);

/*
 * RESTful resources: RESTfulMuxBuilder mounts each RESTfulController at
 * "<prefix>/<resource id>"; the controller routes its five actions by request method.
 *
 *     php eg/restful.php
 */

namespace Pux\Examples;

use Pux\Controller\RESTfulController;
use Pux\MuxBuilder\RESTfulMuxBuilder;
use Pux\RouteExecutor;
use Pux\RouteRequest;

require __DIR__ . '/../vendor/autoload.php';

final class ProductResource extends RESTfulController
{
    public function collectionAction(): string
    {
        return 'list products';
    }

    public function createAction(): string
    {
        return 'create a product';
    }

    public function loadAction(mixed $id): string
    {
        return "load product $id";
    }

    public function updateAction(mixed $id): string
    {
        return "update product $id";
    }

    public function deleteAction(mixed $id): string
    {
        return "delete product $id";
    }
}

$builder = new RESTfulMuxBuilder(null, ['prefix' => '/api']);
$builder->addResource('products', new ProductResource());
$mux = $builder->build();

$requests = [
    RouteRequest::create('GET', '/api/products'),
    RouteRequest::create('POST', '/api/products'),
    RouteRequest::create('GET', '/api/products/3'),
    RouteRequest::create('POST', '/api/products/3'),
    RouteRequest::create('DELETE', '/api/products/3'),
];

foreach ($requests as $request) {
    $route = $mux->dispatchRequest($request);
    printf(
        "%s %s => %s\n",
        $request->getRequestMethod(),
        $request->getPath(),
        $route === null ? 'no route' : RouteExecutor::execute($route),
    );
}
