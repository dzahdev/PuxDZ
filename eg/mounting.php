<?php

declare(strict_types=1);

/*
 * Mounting: mount() attaches a Mux, an Expandable controller or a builder closure under a
 * path. With $expand = true (the default) mount() copies the routes into the parent with the
 * mount path as prefix; with $expand = false the parent keeps one route per mount point, and
 * dispatch() dispatches the rest of the path in the submux.
 *
 *     php eg/mounting.php
 */

namespace Pux\Examples;

use Pux\Controller\ExpandableController;
use Pux\Mux;
use Pux\RouteExecutor;

require __DIR__ . '/../vendor/autoload.php';

final class PageController extends ExpandableController
{
    public function indexAction(): string
    {
        return 'page index';
    }

    public function contactAction(): string
    {
        return 'contact page';
    }
}

function buildMux(bool $expand): Mux
{
    $users = new Mux();
    $users->any('/profile', static fn (string $user): string => "profile of $user");
    $users->any(
        '/posts/:post',
        static fn (string $user, string $post): string => "post $post of $user",
    );

    $mux = new Mux();
    $mux->expand = $expand;
    // A Mux.
    $mux->mount('/users/:user', $users);
    // An Expandable controller: its *Action methods become routes.
    $mux->mount('/pages', new PageController());
    // A closure that fills the Mux it receives (it may instead return a Mux).
    $mux->mount('/api', static function (Mux $api): void {
        $api->any('/status', static fn (): string => 'api ok');
    });

    return $mux;
}

$paths = ['/users/bob/profile', '/users/bob/posts/7', '/pages', '/pages/contact', '/api/status'];

foreach ([true, false] as $expand) {
    $mux = buildMux($expand);
    printf("expand = %s: %d routes in the parent Mux\n", var_export($expand, true), $mux->length());
    foreach ($paths as $path) {
        $route = $mux->dispatch($path);
        echo "  $path => ", $route === null ? 'no route' : RouteExecutor::execute($route), "\n";
    }
}
