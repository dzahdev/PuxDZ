<?php

declare(strict_types=1);

namespace Pux;

/**
 * An object that handles a matched route. Replaces the App interface of the third-party
 * package phpsgi/phpsgi with the same contract.
 *
 * A static route whose callback is a Handler is matched by path prefix, like a submux mount
 * point. RouteExecutor and Mux do not call the method; it is the entry point for application
 * code, Pux\Controller\Controller implements it.
 */
interface Handler
{
    /**
     * The return type is declared in the docblock only, so that implementations written for
     * 2.x without a return type stay compatible with the interface.
     *
     * @param array<array-key, mixed> $environment server parameters plus the keys
     *                                             pux.controller_action and pux.route
     * @param array<array-key, mixed> $response    response in the form [code, headers, body]
     *
     * @return mixed the result of the action, usually a response triple or a string
     */
    public function call(array &$environment, array $response);
}
