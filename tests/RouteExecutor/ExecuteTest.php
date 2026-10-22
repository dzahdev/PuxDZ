<?php

namespace Pux\Tests\RouteExecutor;

use CRUDProductController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pux\Mux;
use Pux\RouteExecutor;
use Pux\Tests\Fixtures\ArgumentBindingController;

/**
 * RouteExecutor::execute runs the callback of a dispatched route and binds route variables
 * and defaults to the action parameters.
 */
final class ExecuteTest extends TestCase
{
    private static function executeDispatched(Mux $mux, string $path): mixed
    {
        $route = $mux->dispatch($path);
        self::assertNotNull($route, sprintf('no route dispatched for %s', $path));

        return RouteExecutor::execute($route);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function actionPaths(): array
    {
        return [
            'root' => ['/', 'index'],
            '/foo' => ['/foo', 'foo'],
            '/bar' => ['/bar', 'bar'],
        ];
    }

    #[DataProvider('actionPaths')]
    public function test_execute_static_route_calls_action_of_new_controller_instance(string $path, string $expected): void
    {
        $mux = new Mux();
        $mux->add('/product/:id', ['ProductController', 'itemAction']);
        $mux->add('/product', ['ProductController', 'listAction']);
        $mux->add('/foo', ['ProductController', 'fooAction']);
        $mux->add('/bar', ['ProductController', 'barAction']);
        $mux->add('/', ['ProductController', 'indexAction']);

        self::assertSame($expected, self::executeDispatched($mux, $path));
    }

    public function test_execute_action_without_parameters_returns_its_result(): void
    {
        $mux = new Mux();
        $mux->add('/hello/show', ['HelloController', 'show']);

        self::assertSame('response', self::executeDispatched($mux, '/hello/show'));
    }

    public function test_execute_passes_route_variable_to_parameter_of_same_name(): void
    {
        $mux = new Mux();
        $mux->add('/hello/:name', ['HelloController', 'index']);

        self::assertSame('Hello John', self::executeDispatched($mux, '/hello/John'));
    }

    public function test_execute_route_added_with_class_colon_method_string_calls_that_method(): void
    {
        $mux = new Mux();
        $mux->add('/', 'PageController:page1');

        self::assertSame('page1', self::executeDispatched($mux, '/'));
    }

    public function test_execute_binds_variables_by_name_when_parameter_names_match(): void
    {
        $mux = new Mux();
        $mux->any('/s/:param1/:param2', [ArgumentBindingController::class, 'named']);

        self::assertSame('p1=x p2=y', self::executeDispatched($mux, '/s/x/y'));
    }

    public function test_execute_uses_route_default_when_optional_variable_is_absent(): void
    {
        $mux = new Mux();
        $mux->any('/k(/:id)', [ArgumentBindingController::class, 'item'], ['default' => ['id' => '5']]);

        self::assertSame('item=5 fmt=html', self::executeDispatched($mux, '/k'));
    }

    /**
     * @see PLAN-port-php82.md L6
     */
    #[Group('known-defect')]
    public function test_execute_uses_falsy_route_default_when_optional_variable_is_absent(): void
    {
        $mux = new Mux();
        $mux->any('/k(/:id)', [ArgumentBindingController::class, 'item'], ['default' => ['id' => '0']]);

        self::assertSame('item=0 fmt=html', self::executeDispatched($mux, '/k'));
    }

    /**
     * Upstream issue #112.
     *
     * @see PLAN-port-php82.md L7
     */
    #[Group('known-defect')]
    public function test_execute_binds_variables_by_position_when_parameter_names_differ(): void
    {
        $mux = new Mux();
        $mux->any('/s/:param1/:param2', [ArgumentBindingController::class, 'two']);

        self::assertSame('a=x b=y', self::executeDispatched($mux, '/s/x/y'));
    }

    /**
     * @see PLAN-port-php82.md L5
     */
    #[Group('known-defect')]
    public function test_execute_closure_callback_returns_its_result(): void
    {
        $mux = new Mux();
        $mux->any('/c/:id', static fn ($id) => "closure $id");

        self::assertSame('closure 5', self::executeDispatched($mux, '/c/5'));
    }

    /**
     * @see Stage 1
     */
    #[Group('pending-stage-1')]
    public function test_execute_controller_json_action_returns_json_response_triple(): void
    {
        $mux = new Mux();
        $mux->add('/json', [CRUDProductController::class, 'jsonAction']);

        self::assertSame(
            [200, ['Content-Type: application/json'], '{"foo":1}'],
            self::executeDispatched($mux, '/json'),
        );
    }
}
