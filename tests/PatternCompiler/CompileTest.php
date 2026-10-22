<?php

namespace Pux\Tests\PatternCompiler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pux\PatternCompiler;

/**
 * PatternCompiler::compile: the result is checked only through preg_match on its 'compiled'
 * regular expression and its 'variables' list, never through the text of the expression.
 */
final class CompileTest extends TestCase
{
    /**
     * @return array<string, string>|null named captures of a match, null when the path does not match
     */
    private static function match(string $pattern, string $path, array $options = []): ?array
    {
        $compiled = PatternCompiler::compile($pattern, $options);
        if (preg_match($compiled['compiled'], $path, $matches) !== 1) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    public function test_compile_two_slash_separated_variables_captures_each_segment(): void
    {
        self::assertSame(['year' => '2013', 'month' => '09'], self::match('/blog/:year/:month', '/blog/2013/09'));
    }

    public function test_compile_two_slash_separated_variables_rejects_path_with_one_segment(): void
    {
        self::assertNull(self::match('/blog/:year/:month', '/blog/2013'));
    }

    public function test_compile_lists_variables_in_pattern_order(): void
    {
        self::assertSame(['year', 'month'], PatternCompiler::compile('/blog/:year/:month')['variables']);
    }

    public function test_compile_variables_separated_by_dash_capture_each_part(): void
    {
        self::assertSame(['year' => '2013', 'month' => '09'], self::match('/blog/:year-:month', '/blog/2013-09'));
    }

    public function test_compile_optional_format_group_matches_path_without_format(): void
    {
        self::assertSame(['id' => '3'], self::match('/blog/:id(.:format)', '/blog/3'));
    }

    public function test_compile_optional_format_group_captures_given_format(): void
    {
        self::assertSame(['id' => '3314', 'format' => 'json'], self::match('/blog/:id(.:format)', '/blog/3314.json'));
    }

    public function test_compile_optional_group_lists_its_variable_after_outer_ones(): void
    {
        $compiled = PatternCompiler::compile('/blog/:id(.:format)', ['default' => ['format' => 'json']]);

        self::assertSame(['id', 'format'], $compiled['variables']);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function formatPaths(): array
    {
        return [
            'json' => ['/blog/23.json', '23', 'json'],
            'xml' => ['/blog/31.xml', '31', 'xml'],
            'non-numeric id' => ['/blog/foo.yaml', 'foo', 'yaml'],
        ];
    }

    #[DataProvider('formatPaths')]
    public function test_compile_optional_format_group_with_default_captures_any_format(string $path, string $id, string $format): void
    {
        self::assertSame(
            ['id' => $id, 'format' => $format],
            self::match('/blog/:id(.:format)', $path, ['default' => ['format' => 'json']]),
        );
    }

    public function test_compile_optional_format_group_rejects_slash_in_place_of_dot(): void
    {
        $options = ['default' => ['format' => 'json']];

        self::assertNull(self::match('/blog/:id(.:format)', '/blog/23/json', $options));
        self::assertNull(self::match('/blog/:id(.:format)', '/blog/23/', $options));
    }

    public function test_compile_trailing_variable_with_default_matches_path_that_gives_it(): void
    {
        self::assertSame(
            ['id' => '23', 'format' => 'json'],
            self::match('/blog/:id.:format', '/blog/23.json', ['default' => ['format' => 'json']]),
        );
    }

    public function test_compile_trailing_variable_with_default_matches_path_that_omits_it(): void
    {
        self::assertSame(['id' => '23'], self::match('/blog/:id.:format', '/blog/23', ['default' => ['format' => 'json']]));
    }

    /**
     * @see PLAN-port-php82.md L8
     */
    #[Group('known-defect')]
    public function test_compile_with_two_optional_groups_matches_both_given(): void
    {
        self::assertSame(['b' => '1', 'd' => '2'], self::match('/a(/:b)/c(/:d)', '/a/1/c/2'));
    }

    /**
     * @see PLAN-port-php82.md L8
     */
    #[Group('known-defect')]
    public function test_compile_with_two_optional_groups_matches_both_omitted(): void
    {
        self::assertSame([], self::match('/a(/:b)/c(/:d)', '/a/c'));
    }

    /**
     * @see PLAN-port-php82.md L9
     */
    #[Group('known-defect')]
    public function test_compile_pattern_starting_with_variable_captures_whole_path(): void
    {
        self::assertSame(['id' => '42'], self::match(':id', '42'));
    }
}
