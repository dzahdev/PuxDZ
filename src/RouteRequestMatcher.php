<?php

declare(strict_types=1);

namespace Pux;

/**
 * Request predicates used for route constraints; implemented by RouteRequest.
 */
interface RouteRequestMatcher
{
    /**
     * @param array<array-key, array<string, string>> $constraints
     */
    public function matchConstraints(array $constraints): bool;

    public function pathEqual(string $path): bool;

    /**
     * @param array<int|string, string> $matches
     *
     * @param-out array<int|string, string> $matches
     */
    public function pathMatch(string $pattern, array &$matches = []): bool;

    public function pathEndWith(string $suffix): bool;

    /**
     * @param array<int|string, string> $matches
     *
     * @param-out array<int|string, string> $matches
     */
    public function hostMatch(string $host, array &$matches = []): bool;

    public function requestMethodEqual(string $requestMethod): bool;

    public function pathContain(string $path): bool;

    public function hostEqual(string $host): bool;

    public function portEqual(int|string $port): bool;

    /**
     * @param array<int|string, string> $matches
     *
     * @param-out array<int|string, string> $matches
     */
    public function queryStringMatch(string $pattern, array &$matches = []): bool;
}
