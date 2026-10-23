<?php

declare(strict_types=1);

/*
 * Route cache: Mux::compile() writes PHP code that rebuilds the Mux through Mux::__set_state();
 * requiring the file returns the Mux without compiling the patterns again. The callbacks must
 * be exportable with var_export(): strings and [class, method] arrays, not closures.
 *
 *     php eg/route_cache.php
 */

namespace Pux\Examples;

use Pux\Mux;

require __DIR__ . '/../vendor/autoload.php';

$cacheFile = sys_get_temp_dir() . '/pux-example-routes-' . getmypid() . '.php';

$mux = new Mux();
$mux->any('/product/:id', ['ProductController', 'itemAction'], ['require' => ['id' => '\d+']]);
$mux->any('/product', ['ProductController', 'listAction']);

try {
    // Throws Pux\Exception\CacheWriteFailed when the file cannot be written.
    $mux->compile($cacheFile);

    // Later, for example in the next request:
    $cached = require $cacheFile;
    if (!$cached instanceof Mux) {
        throw new \UnexpectedValueException("The route cache $cacheFile returns no Mux.");
    }

    $route = $cached->dispatch('/product/42');
    echo implode('::', $route[2] ?? ['no route']), ' id=', $route[3]['vars']['id'] ?? '-', "\n";
} finally {
    unlink($cacheFile);
}
