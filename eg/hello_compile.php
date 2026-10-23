<?php

declare(strict_types=1);

/*
 * Route cache: MuxCompiler loads a route definition file, writes the merged routes to a PHP
 * file, and the cache file is required to get the Mux back without compiling patterns again.
 *
 *     php eg/hello_compile.php   prints "hello world"
 */

namespace Pux\Examples;

use Pux\Mux;
use Pux\MuxCompiler;
use Pux\RouteExecutor;

require __DIR__ . '/../vendor/autoload.php';

final class GreetingController
{
    public function helloAction(string $name): string
    {
        return "hello $name";
    }
}

$cacheFile = tempnam(sys_get_temp_dir(), 'pux-routes-');
if ($cacheFile === false) {
    throw new \RuntimeException('Cannot create a temporary file for the route cache.');
}

try {
    $compiler = new MuxCompiler();
    $compiler->callbackValidation = true;
    $compiler->load(__DIR__ . '/hello_routes.php');
    $compiler->compile($cacheFile);

    $mux = require $cacheFile;
    if (!$mux instanceof Mux) {
        throw new \UnexpectedValueException("The route cache $cacheFile returns no Mux.");
    }

    $route = $mux->dispatch('/hello/world');
    if ($route === null) {
        throw new \UnexpectedValueException('The cached Mux does not dispatch /hello/world.');
    }
    echo RouteExecutor::execute($route), "\n";
} finally {
    unlink($cacheFile);
}
