<?php

declare(strict_types=1);

namespace Pux\Exception;

use InvalidArgumentException;

/**
 * A route pattern or a regular expression cannot be compiled.
 *
 * $pattern is the text the caller wrote (a route pattern such as "/blog/:id(.:format)");
 * $detail is the reason: the position of an unbalanced parenthesis.
 */
final class PatternSyntax extends InvalidArgumentException implements PuxException
{
    private function __construct(string $message, public readonly string $pattern, public readonly string $detail)
    {
        parent::__construct($message);
    }

    public static function unclosedGroup(string $pattern, int $offset): self
    {
        $detail = sprintf("unclosed '(' at offset %d", $offset);

        return new self(sprintf("Route pattern '%s' has an %s.", $pattern, $detail), $pattern, $detail);
    }

    public static function unopenedGroup(string $pattern, int $offset): self
    {
        $detail = sprintf("')' without a matching '(' at offset %d", $offset);

        return new self(sprintf("Route pattern '%s' has a %s.", $pattern, $detail), $pattern, $detail);
    }
}
