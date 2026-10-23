<?php

declare(strict_types=1);

namespace Pux\Exception;

use RuntimeException;

/**
 * A route cache file cannot be written: Mux::compile() or MuxCompiler::compile() could not
 * write the whole code to the path (missing directory, no permission, full disk).
 */
final class CacheWriteFailed extends RuntimeException implements PuxException
{
    private function __construct(string $message, public readonly string $path)
    {
        parent::__construct($message);
    }

    /**
     * @param string|null $warning the warning text of file_put_contents(), when it emitted one
     */
    public static function forFile(string $file, ?string $warning): self
    {
        return new self(
            $warning === null
                ? sprintf('Cannot write the route cache file %s.', $file)
                : sprintf('Cannot write the route cache file %s: %s.', $file, $warning),
            $file,
        );
    }
}
