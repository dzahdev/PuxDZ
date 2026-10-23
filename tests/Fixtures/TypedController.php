<?php

namespace Pux\Tests\Fixtures;

use Pux\Controller\Controller;

/**
 * Controller whose action declares a scalar parameter type, for the conversion of route
 * variables in Controller::call and Controller::runAction.
 */
class TypedController extends Controller
{
    public function itemAction(int $id): int
    {
        return $id;
    }
}
