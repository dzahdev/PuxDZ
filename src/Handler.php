<?php

declare(strict_types=1);

namespace Pux;

/**
 * An object that handles a matched route. Replaces the third-party interface PHPSGI\App with
 * the same contract.
 *
 * A static route whose callback is a Handler is matched by path prefix, like a submux mount
 * point. RouteExecutor and Mux do not call the method; it is the entry point for application
 * code, Pux\Controller\Controller implements it.
 */
interface Handler
{
    /**
     * @param array<string, mixed> $environment PHPSGI-shaped environment; Controller reads the keys
     *                                          pux.controller_action and pux.route
     * @param array<int, mixed>    $response    response in the form [code, headers, body]
     *
     * @return mixed the result of the action, usually a response triple or a string
     */
    public function call(array &$environment, array $response);
}
