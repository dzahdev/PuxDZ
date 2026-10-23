<?php

declare(strict_types=1);

namespace Pux\Tests\RouteExecutor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pux\Mux;
use Pux\RouteExecutor;

/**
 * Route variables are strings. A value bound to a parameter declared with one scalar type
 * (int, float, string, bool, nullable or not) is converted to that type when the conversion
 * loses nothing: an integer string to int, a numeric string or an int to float, an int or a
 * float to string, a boolean word ("1", "0", "true", "false", "on", "off", "yes", "no", "")
 * to bool. Any other value is passed unchanged, so PHP rejects it with a TypeError.
 */
final class ScalarCoercionTest extends TestCase
{
    /**
     * @param array<string, mixed> $options
     */
    private static function execute(string $pattern, string $path, \Closure $action, array $options = []): mixed
    {
        $mux = new Mux();
        $mux->add($pattern, $action, $options);
        $route = $mux->dispatch($path);
        self::assertNotNull($route, sprintf('no route dispatched for %s', $path));

        return RouteExecutor::execute($route);
    }

    public function test_execute_converts_integer_route_variable_for_int_parameter(): void
    {
        self::assertSame(5, self::execute('/item/:id', '/item/5', static fn (int $id): int => $id));
    }

    public function test_execute_converts_integer_route_variable_for_nullable_int_parameter(): void
    {
        self::assertSame(7, self::execute('/item/:id', '/item/7', static fn (?int $id): ?int => $id));
    }

    public function test_execute_converts_numeric_route_variable_for_float_parameter(): void
    {
        self::assertSame(2.5, self::execute('/price/:amount', '/price/2.5', static fn (float $amount): float => $amount));
    }

    public function test_execute_converts_string_route_default_for_int_parameter(): void
    {
        self::assertSame(1, self::execute('/page(/:page)', '/page', static fn (int $page): int => $page, ['default' => ['page' => '1']]));
    }

    public function test_execute_converts_int_route_default_for_string_parameter(): void
    {
        self::assertSame('3', self::execute('/page(/:page)', '/page', static fn (string $page): string => $page, ['default' => ['page' => 3]]));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function booleanWords(): array
    {
        return [
            'true' => ['true', true],
            'false' => ['false', false],
            'one' => ['1', true],
            'zero' => ['0', false],
            'off' => ['off', false],
        ];
    }

    /**
     * Before the conversion, PHP turned every non-empty string except "0" into true, so "false"
     * and "off" were bound as true.
     */
    #[DataProvider('booleanWords')]
    public function test_execute_converts_boolean_word_route_variable_for_bool_parameter(string $word, bool $expected): void
    {
        self::assertSame($expected, self::execute('/flag/:on', '/flag/' . $word, static fn (bool $on): bool => $on));
    }

    public function test_execute_with_non_integer_route_variable_for_int_parameter_throws_type_error(): void
    {
        $this->expectException(\TypeError::class);

        self::execute('/item/:id', '/item/abc', static fn (int $id): int => $id);
    }

    public function test_execute_with_fractional_route_variable_for_int_parameter_throws_type_error(): void
    {
        $this->expectException(\TypeError::class);

        self::execute('/item/:id', '/item/2.5', static fn (int $id): int => $id);
    }
}
