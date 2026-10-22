<?php

namespace Pux\Tests\RouteExecutor;

use PHPUnit\Framework\TestCase;
use Pux\Exception\InvalidCallback;
use Pux\Tests\Fixtures\GreetingController;

/**
 * Controller::runAction (the target of Controller::forward) calls the <action>Action method of
 * the controller and fails with a Pux exception when that method does not exist.
 */
final class ControllerRunActionTest extends TestCase
{
    /**
     * Failed before the R3 fix with Error 'Class "Pux\Controller\Exception" not found'
     * (Exception is not imported in namespace Pux\Controller).
     *
     * @see PLAN-port-php82.md R3
     */
    public function test_run_action_of_missing_action_method_throws_invalid_callback_naming_method(): void
    {
        $this->expectException(InvalidCallback::class);
        $this->expectExceptionMessage('Method goodbyeAction not found in controller Pux\Tests\Fixtures\GreetingController.');

        (new GreetingController())->runAction('goodbye');
    }
}
