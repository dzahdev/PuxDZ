<?php

declare(strict_types=1);

namespace Pux;

use Pux\Exception\PatternSyntax;

/**
 * preg_match that throws instead of warning: an expression that does not compile (or a match
 * that fails at run time, for example on the backtrack limit) throws PatternSyntax with the
 * expression, the PCRE warning text and preg_last_error_msg().
 *
 * @internal
 */
final class Pcre
{
    /**
     * @param array<int|string, string> $matches
     *
     * @throws PatternSyntax
     */
    public static function match(string $regex, string $subject, array &$matches = []): bool
    {
        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;

            return true;
        }, E_WARNING);

        try {
            $result = preg_match($regex, $subject, $matches);
        } finally {
            restore_error_handler();
        }

        if ($result === false) {
            throw PatternSyntax::invalidRegex($regex, $warning, preg_last_error_msg());
        }

        return $result === 1;
    }
}
