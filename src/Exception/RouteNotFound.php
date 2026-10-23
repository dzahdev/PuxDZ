<?php

declare(strict_types=1);

namespace Pux\Exception;

use RuntimeException;

/**
 * No route has the id given to Mux::url(). Extends RuntimeException, the class url() threw in
 * 2.x, so existing catch blocks keep working. A path that matches no route is not this
 * exception: dispatch() returns null for it.
 */
final class RouteNotFound extends RuntimeException implements PuxException
{
    private function __construct(string $message, public readonly int|string $id)
    {
        parent::__construct($message);
    }

    public static function forId(int|string $id): self
    {
        return new self('Named route not found for id: ' . $id, $id);
    }
}
