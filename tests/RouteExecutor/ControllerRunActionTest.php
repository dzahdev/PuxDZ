<?php

namespace Pux\Tests\RouteExecutor;

use PHPUnit\Framework\TestCase;
use Pux\Exception\InvalidCallback;
use Pux\Exception\MissingArgument;
use Pux\Tests\Fixtures\GreetingController;
use Pux\Tests\Fixtures\TypedController;

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

    /**
     * Failed before the L7 fix with ArgumentCountError.
     *
     * @see PLAN-port-php82.md L7
     */
    public function test_run_action_without_var_for_required_parameter_throws_missing_argument_naming_it(): void
    {
        $this->expectException(MissingArgument::class);
        $this->expectExceptionMessage(GreetingController::class . '::helloAction(): no route variable or route default for required parameter $name.');

        (new GreetingController())->runAction('hello');
    }

    /**
     * Failed before the L7 fix with ArgumentCountError.
     *
     * @see PLAN-port-php82.md L7
     */
    public function test_run_action_binds_var_by_position_when_parameter_name_differs(): void
    {
        self::assertSame('hi Bo', (new GreetingController())->runAction('hello', ['who' => 'Bo']));
    }

    public function test_run_action_binds_var_by_name(): void
    {
        self::assertSame('hi Cy', (new GreetingController())->runAction('hello', ['name' => 'Cy']));
    }

    public function test_run_action_converts_integer_var_for_int_parameter(): void
    {
        self::assertSame(9, (new TypedController())->runAction('item', ['id' => '9']));
    }
}
