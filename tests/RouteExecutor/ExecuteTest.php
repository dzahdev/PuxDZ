<?php

declare(strict_types=1);

namespace Pux\Tests\RouteExecutor;

use CRUDProductController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pux\Exception\InvalidCallback;
use Pux\Exception\MissingArgument;
use Pux\Mux;
use Pux\RouteExecutor;
use Pux\Tests\Fixtures\ArgumentBindingController;
use Pux\Tests\Fixtures\PrefixedAction;

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
     * Failed before the L6 fix with ArgumentCountError: a default that is falsy ('0') was not bound.
     *
     * @see PLAN-port-php82.md L6
     */
    public function test_execute_uses_falsy_route_default_when_optional_variable_is_absent(): void
    {
        $mux = new Mux();
        $mux->any('/k(/:id)', [ArgumentBindingController::class, 'item'], ['default' => ['id' => '0']]);

        self::assertSame('item=0 fmt=html', self::executeDispatched($mux, '/k'));
    }

    /**
     * The route default applies to the variable of an optional group that is not in the path,
     * also when a required variable follows the group. Failed before the L38 fix with
     * "b= c=1": the absent group was bound as the empty string.
     *
     * @see PLAN-port-php82.md L38
     */
    public function test_execute_uses_route_default_for_omitted_optional_group_before_required_variable(): void
    {
        $mux = new Mux();
        $mux->any('/a(/:b)/:c', static fn (string $b, string $c): string => "b=$b c=$c", ['default' => ['b' => 'x']]);

        self::assertSame('b=x c=1', self::executeDispatched($mux, '/a/1'));
    }

    /**
     * Upstream issue #112. Failed before the L7 fix with ArgumentCountError: binding was by
     * name only.
     *
     * @see PLAN-port-php82.md L7
     */
    public function test_execute_binds_variables_by_position_when_parameter_names_differ(): void
    {
        $mux = new Mux();
        $mux->any('/s/:param1/:param2', [ArgumentBindingController::class, 'two']);

        self::assertSame('a=x b=y', self::executeDispatched($mux, '/s/x/y'));
    }

    /**
     * Binding by name comes first: $param2 takes its variable by name, $a takes the remaining
     * named variable param1 by position. Failed before the L7 fix with ArgumentCountError.
     *
     * @see PLAN-port-php82.md L7
     */
    public function test_execute_binds_variable_left_after_name_binding_to_remaining_parameter(): void
    {
        $mux = new Mux();
        $mux->any('/s/:param1/:param2', [ArgumentBindingController::class, 'nameAndPosition']);

        self::assertSame('a=x param2=y', self::executeDispatched($mux, '/s/x/y'));
    }

    /**
     * Failed before the L7 fix with ArgumentCountError.
     *
     * @see PLAN-port-php82.md L7
     */
    public function test_execute_without_value_for_required_parameter_throws_missing_argument_naming_parameter_and_callback(): void
    {
        $mux = new Mux();
        $mux->any('/s/:param1', [ArgumentBindingController::class, 'two']);

        $this->expectException(MissingArgument::class);
        $this->expectExceptionMessage(ArgumentBindingController::class . '::two(): no route variable or route default for required parameter $b.');

        self::executeDispatched($mux, '/s/x');
    }

    /**
     * A required parameter that allows null is still required: it is not filled with null.
     * Failed before the L7 fix with ArgumentCountError.
     *
     * @see PLAN-port-php82.md L7
     */
    public function test_execute_without_value_for_required_nullable_parameter_throws_missing_argument(): void
    {
        $mux = new Mux();
        $mux->any('/n', [ArgumentBindingController::class, 'nullable']);

        $this->expectException(MissingArgument::class);
        $this->expectExceptionMessage('required parameter $value');

        self::executeDispatched($mux, '/n');
    }

    /**
     * Failed before the L5 fix with Error "Cannot use object of type Closure as array".
     *
     * @see PLAN-port-php82.md L5
     */
    public function test_execute_closure_callback_returns_its_result(): void
    {
        $mux = new Mux();
        $mux->any('/c/:id', static fn ($id) => "closure $id");

        self::assertSame('closure 5', self::executeDispatched($mux, '/c/5'));
    }

    /**
     * Failed before the L5 fix with ReflectionException 'Class "s" does not exist' (the first character of the name was taken as a class).
     *
     * @see PLAN-port-php82.md L5
     */
    public function test_execute_function_name_callback_binds_route_variable(): void
    {
        $mux = new Mux();
        $mux->any('/upper/:string', 'strtoupper');

        self::assertSame('ABC', self::executeDispatched($mux, '/upper/abc'));
    }

    /**
     * Passed before the L5 fix as well; specifies the [object, method] form next to the others.
     *
     * @see PLAN-port-php82.md L5
     */
    public function test_execute_object_and_method_callback_calls_method_of_given_object(): void
    {
        $mux = new Mux();
        $mux->any('/d/:id', [new PrefixedAction('given'), 'describe']);

        self::assertSame('given described 7', self::executeDispatched($mux, '/d/7'));
    }

    /**
     * Failed before the L5 fix with Error "Cannot use object of type PrefixedAction as array".
     *
     * @see PLAN-port-php82.md L5
     */
    public function test_execute_invokable_object_callback_calls_its_invoke_method(): void
    {
        $mux = new Mux();
        $mux->any('/i/:id', new PrefixedAction('given'));

        self::assertSame('given invoked 7', self::executeDispatched($mux, '/i/7'));
    }

    /**
     * Failed before the L5 fix with ReflectionException instead of InvalidCallback.
     *
     * @see PLAN-port-php82.md L5
     */
    public function test_execute_unknown_function_name_callback_throws_invalid_callback_naming_it(): void
    {
        $mux = new Mux();
        $mux->any('/n', 'pux_no_such_function');

        $this->expectException(InvalidCallback::class);
        $this->expectExceptionMessage('pux_no_such_function');

        self::executeDispatched($mux, '/n');
    }

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
