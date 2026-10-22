<?php

namespace Pux\Tests\RouteExecutor;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pux\Tests\Fixtures\GreetingController;

/**
 * Controller::call binds route variables to action parameters with the same rules as
 * RouteExecutor::execute (one argument-binding algorithm, see ADR 0002).
 *
 * @see Stage 1
 */
final class ControllerCallTest extends TestCase
{
    /**
     * Fails today because Controller::call reads the undefined variable $route instead of the
     * matched route and throws the unimported Exception class.
     *
     * @see PLAN-port-php82.md R2
     * @see Stage 1
     */
    #[Group('known-defect')]
    #[Group('pending-stage-1')]
    public function test_call_without_variable_for_required_parameter_throws_exception_naming_parameter(): void
    {
        $controller = new GreetingController();
        $environment = [
            'pux.controller_action' => 'helloAction',
            'pux.route' => [false, '/x', [$controller, 'helloAction'], []],
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('name');

        $controller->call($environment, []);
    }
}
