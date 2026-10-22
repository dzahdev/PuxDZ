<?php

// Transitional shim, removed in Stage 2.
//
// Replaces the global functions of corneltek/phpunit-testmore that the inherited test suite
// calls, so the suite runs without that package. Each function forwards to one
// PHPUnit\Framework\Assert method. Stage 2 rewrites every call site to a PHPUnit assertion
// and deletes this file.

use PHPUnit\Framework\Assert;

if (!function_exists('ok')) {
    function ok(mixed $value, string $msg = ''): void
    {
        Assert::assertNotEmpty($value, $msg);
    }
}

if (!function_exists('is')) {
    function is(mixed $expected, mixed $actual, string $msg = ''): void
    {
        Assert::assertEquals($expected, $actual, $msg);
    }
}

if (!function_exists('count_ok')) {
    function count_ok(int $expected, \Countable|iterable $collection, string $msg = ''): void
    {
        Assert::assertCount($expected, $collection, $msg);
    }
}

if (!function_exists('fail')) {
    function fail(string $msg = ''): never
    {
        Assert::fail($msg);
    }
}

if (!function_exists('same_ok')) {
    function same_ok(mixed $expected, mixed $actual, string $msg = ''): void
    {
        Assert::assertSame($expected, $actual, $msg);
    }
}

if (!function_exists('path_ok')) {
    function path_ok(string $path, string $msg = ''): void
    {
        Assert::assertFileExists($path, $msg);
    }
}
