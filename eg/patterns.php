<?php

declare(strict_types=1);

/*
 * Pattern syntax: ":name" variables, optional groups in parentheses, 'require' expressions,
 * 'default' values, a catch-all variable and literal white space. Each line prints the named
 * variables that dispatch() captured, or "no route".
 *
 *     php eg/patterns.php
 */

namespace Pux\Examples;

use Pux\Mux;

require __DIR__ . '/../vendor/autoload.php';

$mux = new Mux();
// ":name" matches up to the next separator; a group in parentheses is optional.
$mux->any('/blog/:year/:month(/:slug)', 'blog');
// 'require' replaces the default expression of a variable.
$mux->any('/product/:id(.:format)', 'product', [
    'require' => ['id' => '\d+', 'format' => 'json|html'],
    'default' => ['format' => 'html'],
]);
// A catch-all variable is a 'require' expression that accepts "/".
$mux->any('/files/:path', 'files', ['require' => ['path' => '.+']]);
// White space in a pattern is matched literally.
$mux->any('/my documents/:name', 'documents');

$paths = ['/blog/2026/10', '/blog/2026/10/release', '/product/42.json', '/product/42',
    '/product/abc', '/files/docs/a/b.txt', '/my documents/cv', '/mydocuments/cv'];

foreach ($paths as $path) {
    $route = $mux->dispatch($path);
    if ($route === null) {
        echo "$path => no route\n";
        continue;
    }
    $vars = array_filter($route[3]['vars'], is_string(...), ARRAY_FILTER_USE_KEY);
    $json = json_encode($vars, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    echo "$path => $route[2] $json\n";
}
