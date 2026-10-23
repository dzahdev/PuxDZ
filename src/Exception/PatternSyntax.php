<?php

declare(strict_types=1);

namespace Pux\Exception;

use InvalidArgumentException;

/**
 * A route pattern or a regular expression cannot be compiled.
 *
 * $pattern is the text the caller wrote: a route pattern such as "/blog/:id(.:format)" or a
 * regular expression given to a RouteRequest predicate. $detail is the reason: the position of an
 * unbalanced parenthesis, or the PCRE warning text followed by preg_last_error_msg().
 */
final class PatternSyntax extends InvalidArgumentException implements PuxException
{
    private function __construct(
        string $message,
        public readonly string $pattern,
        public readonly string $detail,
        ?self $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
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

    public static function invalidRegex(string $regex, ?string $warning, string $pcreError): self
    {
        $detail = $warning === null ? $pcreError : sprintf('%s (%s)', $warning, $pcreError);

        return new self(sprintf("Regular expression '%s' is invalid: %s.", $regex, $detail), $regex, $detail);
    }

    public static function invalidRoutePattern(string $pattern, self $cause): self
    {
        return new self(
            sprintf("Route pattern '%s' compiles to an invalid regular expression, check its 'require' option: %s.", $pattern, $cause->detail),
            $pattern,
            $cause->detail,
            $cause,
        );
    }
}
