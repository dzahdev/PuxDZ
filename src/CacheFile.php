<?php

declare(strict_types=1);

namespace Pux;

use Pux\Exception\CacheWriteFailed;

/**
 * file_put_contents() that throws instead of warning: a write that fails or writes fewer bytes
 * than the code throws CacheWriteFailed with the path and the warning text.
 *
 * @internal
 */
final class CacheFile
{
    /**
     * @throws CacheWriteFailed
     */
    public static function write(string $file, string $code): void
    {
        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;

            return true;
        }, E_WARNING);

        try {
            $written = file_put_contents($file, $code);
        } finally {
            restore_error_handler();
        }

        if ($written !== strlen($code)) {
            throw CacheWriteFailed::forFile($file, $warning);
        }
    }
}
