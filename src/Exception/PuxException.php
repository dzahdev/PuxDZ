<?php

declare(strict_types=1);

namespace Pux\Exception;

use Throwable;

/**
 * Marker of every exception thrown by Pux for a routing or execution error (plan invariant 4).
 * Catch it to handle any Pux failure; catch a concrete class to handle one kind.
 */
interface PuxException extends Throwable
{
}
