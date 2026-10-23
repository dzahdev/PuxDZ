<?php

declare(strict_types=1);

namespace Pux\Exception;

use LogicException;

/**
 * A required parameter of an action has neither a route variable nor a route default to bind.
 */
final class MissingArgument extends LogicException implements PuxException
{
    public static function forParameter(string $parameter, string $callback): self
    {
        return new self(sprintf(
            'Cannot call %s(): no route variable or route default for required parameter $%s.',
            $callback,
            $parameter,
        ));
    }
}
