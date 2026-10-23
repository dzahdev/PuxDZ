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
        return self::guarded($regex, static function () use ($regex, $subject, &$matches): int|false {
            return preg_match($regex, $subject, $matches);
        });
    }

    /**
     * match() whose $matches holds only the groups that took part in the match: a group of an
     * optional part that is not in $subject is left out, also when a later group matched
     * (preg_match() reports such a group as the empty string). A group that matched the empty
     * string is kept.
     *
     * @param array<int|string, string> $matches
     *
     * @throws PatternSyntax
     */
    public static function matchParticipating(string $regex, string $subject, array &$matches = []): bool
    {
        $groups = [];
        $matched = self::guarded($regex, static function () use ($regex, $subject, &$groups): int|false {
            return preg_match($regex, $subject, $groups, PREG_UNMATCHED_AS_NULL);
        });
        $matches = array_filter($groups, static fn (?string $group): bool => $group !== null);

        return $matched;
    }

    /**
     * Runs $match (a preg_match() call) with the PCRE warning captured instead of emitted.
     *
     * @param \Closure(): (int|false) $match
     *
     * @throws PatternSyntax
     */
    private static function guarded(string $regex, \Closure $match): bool
    {
        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;

            return true;
        }, E_WARNING);

        try {
            $result = $match();
        } finally {
            restore_error_handler();
        }

        if ($result === false) {
            throw PatternSyntax::invalidRegex($regex, $warning, preg_last_error_msg());
        }

        return $result === 1;
    }
}
