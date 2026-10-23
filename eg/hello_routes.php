<?php

declare(strict_types=1);

/*
 * A route definition file: it returns a Mux. eg/hello_compile.php compiles it into a route
 * cache file with MuxCompiler.
 */

namespace Pux\Examples;

use Pux\Mux;

require_once __DIR__ . '/../vendor/autoload.php';

$mux = new Mux();
$mux->add('/hello/:name', 'Pux\Examples\GreetingController::helloAction', [
    'require' => ['name' => '[a-z]+'],
]);

return $mux;
