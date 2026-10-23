<?php

declare(strict_types=1);

/*
 * Request constraints: the options 'method', 'domain' and 'secure' are checked against the
 * RouteRequest given to dispatch(). Without a request, dispatch() builds one with
 * RouteRequest::fromServer() only when a route with a constraint matches the path.
 *
 *     php eg/constraints.php
 */

namespace Pux\Examples;

use Pux\Mux;
use Pux\RequestMethod;
use Pux\RouteRequest;

require __DIR__ . '/../vendor/autoload.php';

$mux = new Mux();
$mux->add('/items', 'replaceItems', ['method' => RequestMethod::PUT]);
$mux->add('/items', 'listItems', ['method' => 'get']);
$mux->any('/admin', 'admin', ['domain' => 'admin.example.com', 'secure' => true]);
$mux->any('/about', 'about');

$requests = [
    'PUT /items' => RouteRequest::create('PUT', '/items'),
    'GET /items' => RouteRequest::create('GET', '/items'),
    'POST /items' => RouteRequest::create('POST', '/items'),
    'GET https://admin.example.com/admin' => RouteRequest::create('GET', '/admin', [
        'HTTP_HOST' => 'admin.example.com',
        'HTTPS' => 'on',
    ]),
    'GET http://admin.example.com/admin' => RouteRequest::create('GET', '/admin', [
        'HTTP_HOST' => 'admin.example.com',
    ]),
    'GET https://www.example.com/admin' => RouteRequest::create('GET', '/admin', [
        'HTTP_HOST' => 'www.example.com',
        'HTTPS' => 'on',
    ]),
];

foreach ($requests as $label => $request) {
    echo $label, ' => ', $mux->dispatchRequest($request)[2] ?? 'no route', "\n";
}

// A route without constraints needs no request, so this works on the command line too.
echo '/about without a request => ', $mux->dispatch('/about')[2] ?? 'no route', "\n";
