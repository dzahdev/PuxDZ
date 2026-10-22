<?php

namespace Pux\Tests\Fixtures;

use Pux\Controller\Controller;

/**
 * Controller with one action that requires a route variable; used for Controller::call.
 */
class GreetingController extends Controller
{
    public function helloAction($name)
    {
        return "hi $name";
    }
}
