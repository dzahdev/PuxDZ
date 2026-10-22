<?php

namespace Pux\Tests\Fixtures;

use Pux\Handler;

/**
 * Test fixture: the smallest Pux\Handler, returns the path of the matched route as the body.
 */
final class EchoHandler implements Handler
{
    public function call(array &$environment, array $response)
    {
        return [200, [], $environment['pux.route'][1]];
    }
}
