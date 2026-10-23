<?php

declare(strict_types=1);

/*
 * Controller expansion: mount() turns the public *Action methods of an ExpandableController
 * into routes under the mount path. The path comes from the #[Route] attribute, else from the
 * method name (helloAction is /hello, indexAction is the mount path itself).
 *
 *     php eg/expand.php              prints the responses of /my, /my/hello and /my/foo
 *     php eg/expand.php /my/hello    prints the response of /my/hello
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
        return 'MyController::indexAction()';
    }

    public function helloAction(): string
    {
        return 'MyController::helloAction()';
    }

    #[Route('/foo', method: RequestMethod::GET)]
    public function overrideAction(): string
    {
        return 'MyController::overrideAction() at /foo';
    }
}

$mux = new Mux();
$mux->mount('/my', new MyController());

$paths = isset($argv[1]) ? [$argv[1]] : ['/my', '/my/hello', '/my/foo'];
foreach ($paths as $path) {
    $route = $mux->dispatch($path, new RouteRequest('GET', $path));
    printf("%s => %s\n", $path, $route === null ? '404' : RouteExecutor::execute($route));
}
