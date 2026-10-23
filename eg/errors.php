<?php

declare(strict_types=1);

/*
 * Errors: a routing or execution error throws an exception that implements
 * Pux\Exception\PuxException; invalid arguments throw the standard PHP exceptions. A path that
 * matches no route is not an error: dispatch() returns null.
 *
 *     php eg/errors.php
 */

namespace Pux\Examples;

use Pux\Exception\CacheWriteFailed;
use Pux\Exception\InvalidCallback;
use Pux\Exception\MissingArgument;
use Pux\Exception\PatternSyntax;
use Pux\Exception\PuxException;
use Pux\Exception\RouteNotFound;
use Pux\Mux;
use Pux\RouteExecutor;
use Pux\RouteRequest;

require __DIR__ . '/../vendor/autoload.php';

final class GreetController
{
    public function greetAction(string $name): string
    {
        return "hello $name";
    }
}

/**
 * Runs $action and prints the short name of the exception it throws.
 *
 * @param class-string<\Throwable> $expected
 */
function expectError(string $expected, callable $action): void
{
    try {
        $action();
    } catch (\Throwable $error) {
        if (!$error instanceof $expected) {
            throw $error;
        }
        $detail = match (true) {
            $error instanceof PatternSyntax => "pattern {$error->pattern}",
            $error instanceof CacheWriteFailed => 'file ' . basename($error->path),
            $error instanceof RouteNotFound => "id {$error->id}",
            default => $error->getMessage(),
        };
        $kind = $error instanceof PuxException ? ' (PuxException)' : '';
        printf("%s%s: %s\n", (new \ReflectionClass($error))->getShortName(), $kind, $detail);

        return;
    }

    throw new \LogicException("No $expected was thrown.");
}

$mux = new Mux();
echo 'dispatch of an unknown path returns ', var_export($mux->dispatch('/missing'), true), "\n";

expectError(PatternSyntax::class, static fn () => $mux->any('/blog/(:id', 'post'));
expectError(
    PatternSyntax::class,
    static fn () => $mux->any('/blog/:id', 'post', ['require' => ['id' => '[0-9']]),
);
expectError(
    InvalidCallback::class,
    static fn () => RouteExecutor::execute([false, '/x', ['NoSuchController', 'indexAction'], []]),
);
$greetRoute = [false, '/greet', [GreetController::class, 'greetAction'], []];
expectError(MissingArgument::class, static fn () => RouteExecutor::execute($greetRoute));
expectError(CacheWriteFailed::class, static fn () => $mux->compile(__FILE__ . '/routes.php'));
expectError(RouteNotFound::class, static fn () => $mux->url('missing'));
expectError(\ValueError::class, static fn () => $mux->any('/x', 'x', ['method' => 'FETCH']));
expectError(
    \InvalidArgumentException::class,
    static fn () => RouteRequest::fromServer(['REQUEST_URI' => '/']),
);
