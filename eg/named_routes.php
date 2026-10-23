<?php

declare(strict_types=1);

/*
 * Named routes: the option 'id' registers a route under a name, and url() builds a path from
 * it. Optional groups whose variables are not given are dropped. Values are inserted as given,
 * so the caller encodes them.
 *
 *     php eg/named_routes.php
 */

namespace Pux\Examples;

use Pux\Mux;

require __DIR__ . '/../vendor/autoload.php';

$mux = new Mux();
$mux->any('/', 'home', ['id' => 'home']);
$mux->any('/blog/:year/:month(/:slug)', 'post', ['id' => 'blog.post']);

echo $mux->url('home'), "\n";
echo $mux->url('blog.post', ['year' => 2026, 'month' => 10]), "\n";
echo $mux->url('blog.post', ['year' => 2026, 'month' => 10, 'slug' => rawurlencode('a b')]), "\n";
