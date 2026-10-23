<?php

declare(strict_types=1);

/*
 * Controller expansion: mount() turns the public *Action methods of an ExpandableController
 * into routes under the mount path. The path comes from the #[Route] attribute, else from the
 * method name: indexAction is the mount path itself, helloAction is /hello, pageUpdateAction is
 * /page/update.
 *
 *     php eg/expand.php
 */

namespace Pux\Examples;

use Pux\Attribute\Route;
use Pux\Controller\ExpandableController;
use Pux\Mux;
use Pux\RequestMethod;
use Pux\RouteExecutor;
use Pux\RouteRequest;

require __DIR__ . '/../vendor/autoload.php';

final class MyController extends ExpandableController
{
    public function indexAction(): string
    {
        return 'index';
    }

    public function helloAction(): string
    {
        return 'hello';
    }

    public function pageUpdateAction(): string
    {
        return 'page update';
    }

    #[Route('/items/:id', method: RequestMethod::POST)]
    public function saveAction(int $id): string
    {
        return "item $id saved";
    }
}

$mux = new Mux();
$mux->mount('/my', new MyController());

$requests = [
    RouteRequest::create('GET', '/my'),
    RouteRequest::create('GET', '/my/hello'),
    RouteRequest::create('GET', '/my/page/update'),
    RouteRequest::create('POST', '/my/items/5'),
    RouteRequest::create('GET', '/my/items/5'),
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
