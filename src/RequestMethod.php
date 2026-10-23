<?php

declare(strict_types=1);

namespace Pux;

use ValueError;

/**
 * HTTP request method of a route constraint. Routes store the integer value in the 'method'
 * option, so a route cache written by Mux::export() holds plain integers. The values are those
 * of the deprecated REQUEST_METHOD_* constants (src/constants.php).
 */
enum RequestMethod: int
{
    case GET = 1;
    case POST = 2;
    case PUT = 3;
    case DELETE = 4;
    case PATCH = 5;
    case HEAD = 6;
    case OPTIONS = 7;

    /**
     * The case whose name equals $name, ignoring case ("post" is POST).
     *
     * @throws ValueError when no case has that name, as BackedEnum::from() does for a value
     */
    public static function fromName(string $name): self
    {
        return self::tryFromName($name) ?? throw new ValueError(sprintf(
            '"%s" is not a request method of %s; expected one of %s.',
            $name,
            self::class,
            implode(', ', array_map(static fn (self $case): string => $case->name, self::cases())),
        ));
    }

    /**
     * The case whose name equals $name, ignoring case; null for any other method name.
     */
    public static function tryFromName(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if (strcasecmp($case->name, $name) === 0) {
                return $case;
            }
        }

        return null;
    }
}
