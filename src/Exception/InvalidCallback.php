<?php

declare(strict_types=1);

namespace Pux\Exception;

use LogicException;

/**
 * A route callback or controller action names a class or a method that does not exist.
 */
final class InvalidCallback extends LogicException implements PuxException
{
    public static function classNotFound(string $class): self
    {
        return new self(sprintf('Controller %s does not exist.', $class));
    }

    public static function methodNotFound(string $class, string $method): self
    {
        return new self(sprintf('Method %s not found in controller %s.', $method, $class));
    }
}
