<?php

declare(strict_types=1);

namespace Pux\Tests\Fixtures;

use Pux\Controller\Controller;

/**
 * Controller whose action returns the method and path of the request that
 * Controller::getRequest builds from the environment given to Controller::call.
 */
class RequestEchoController extends Controller
{
    public function requestAction()
    {
        $request = $this->getRequest();

        return [$request->getRequestMethod(), $request->getPath()];
    }
}
