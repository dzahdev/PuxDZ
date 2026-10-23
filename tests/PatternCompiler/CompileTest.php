<?php

declare(strict_types=1);

namespace Pux\Tests\PatternCompiler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pux\Exception\PatternSyntax;
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

    public function test_compile_variable_followed_by_text_captures_variable_and_requires_text(): void
    {
        self::assertSame(['id' => '5'], self::match('/user/:id/edit', '/user/5/edit'));
        self::assertNull(self::match('/user/:id/edit', '/user/5/6/edit'));
    }

    public function test_compile_treats_dot_in_text_as_literal_character(): void
    {
        self::assertSame(['id' => '3'], self::match('/v1.0/:id', '/v1.0/3'));
        self::assertNull(self::match('/v1.0/:id', '/v1x0/3'));
    }

    public function test_compile_with_require_accepts_value_matching_requirement(): void
    {
        self::assertSame(['name' => '42'], self::match('/hello/:name', '/hello/42', ['require' => ['name' => '\d+']]));
    }

    public function test_compile_with_require_rejects_value_violating_requirement(): void
    {
        self::assertNull(self::match('/hello/:name', '/hello/john', ['require' => ['name' => '\d+']]));
    }

    public function test_compile_with_require_may_let_variable_span_separators(): void
    {
        self::assertSame(
            ['filters' => 'a/b'],
            self::match('/search/:filters', '/search/a/b', ['require' => ['filters' => '.+']]),
        );
    }

    public function test_compile_with_require_applies_inside_optional_group(): void
    {
        $options = ['require' => ['format' => 'json|xml']];

        self::assertSame(['id' => '3', 'format' => 'json'], self::match('/blog/:id(.:format)', '/blog/3.json', $options));
        self::assertNull(self::match('/blog/:id(.:format)', '/blog/3.yaml', $options));
    }

    public function test_compile_with_defaults_for_two_trailing_variables_makes_both_optional(): void
    {
        $options = ['default' => ['year' => '2000', 'month' => '1']];

        self::assertSame(['year' => '2013', 'month' => '09'], self::match('/blog/:year/:month', '/blog/2013/09', $options));
        self::assertSame(['year' => '2013'], self::match('/blog/:year/:month', '/blog/2013', $options));
        self::assertSame([], self::match('/blog/:year/:month', '/blog', $options));
    }

    public function test_compile_with_default_for_last_variable_only_keeps_earlier_variable_required(): void
    {
        self::assertNull(self::match('/blog/:year/:month', '/blog', ['default' => ['month' => '1']]));
    }

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function nestedOptionalPaths(): array
    {
        return [
            'both omitted' => ['/a', []],
            'outer given' => ['/a/1', ['b' => '1']],
            'both given' => ['/a/1/2', ['b' => '1', 'c' => '2']],
        ];
    }

    /**
     * @param array<string, string> $expected
     */
    #[DataProvider('nestedOptionalPaths')]
    public function test_compile_nested_optional_groups_match_each_depth(string $path, array $expected): void
    {
        self::assertSame($expected, self::match('/a(/:b(/:c))', $path));
    }

    public function test_compile_nested_optional_groups_list_variables_in_pattern_order(): void
    {
        self::assertSame(['b', 'c'], PatternCompiler::compile('/a(/:b(/:c))')['variables']);
    }

    public function test_compile_optional_group_followed_by_text_matches_with_and_without_group(): void
    {
        self::assertSame([], self::match('/a(/:b)/c', '/a/c'));
        self::assertSame(['b' => '1'], self::match('/a(/:b)/c', '/a/1/c'));
    }

    /**
     * Failed before the L8 fix: the greedy group expression of the old tokenizer joined both
     * groups into one.
     *
     * @see PLAN-port-php82.md L8
     */
    public function test_compile_with_two_optional_groups_matches_both_given(): void
    {
        self::assertSame(['b' => '1', 'd' => '2'], self::match('/a(/:b)/c(/:d)', '/a/1/c/2'));
    }

    /**
     * Failed before the L8 fix: the greedy group expression of the old tokenizer joined both
     * groups into one.
     *
     * @see PLAN-port-php82.md L8
     */
    public function test_compile_with_two_optional_groups_matches_both_omitted(): void
    {
        self::assertSame([], self::match('/a(/:b)/c(/:d)', '/a/c'));
    }

    /**
     * The tokenizer that replaced the greedy group regex tracks parenthesis depth, so an
     * unbalanced group is a syntax error instead of literal text.
     *
     * @see PLAN-port-php82.md L8
     */
    public function test_compile_with_unclosed_optional_group_throws_pattern_syntax(): void
    {
        $this->expectException(PatternSyntax::class);
        $this->expectExceptionMessage('/a(/:b');

        PatternCompiler::compile('/a(/:b');
    }

    /**
     * @see PLAN-port-php82.md L8
     */
    public function test_compile_with_closing_parenthesis_without_group_throws_pattern_syntax(): void
    {
        $this->expectException(PatternSyntax::class);
        $this->expectExceptionMessage('/a/:b)');

        PatternCompiler::compile('/a/:b)');
    }

    /**
     * Failed before the L28 fix: the route compiled, and preg_match on it emitted a warning.
     *
     * @see PLAN-port-php82.md L28
     */
    public function test_compile_with_require_that_is_not_a_regex_throws_pattern_syntax(): void
    {
        $this->expectException(PatternSyntax::class);
        $this->expectExceptionMessage('/item/:id');

        PatternCompiler::compile('/item/:id', ['require' => ['id' => '[']]);
    }

    /**
     * Failed before the L9 fix: a colon without a separator character before it was text.
     *
     * @see PLAN-port-php82.md L9
     */
    public function test_compile_pattern_starting_with_variable_captures_whole_path(): void
    {
        self::assertSame(['id' => '42'], self::match(':id', '42'));
    }

    /**
     * @see PLAN-port-php82.md L9
     */
    public function test_compile_variable_without_separator_neighbours_excludes_slash(): void
    {
        self::assertNull(self::match(':id', '4/2'));
    }

    /**
     * @see PLAN-port-php82.md L9
     */
    public function test_compile_optional_group_starting_with_variable_captures_it(): void
    {
        self::assertSame(['id' => '7', 'format' => 'json'], self::match('/item/:id(:format)', '/item/7json', ['require' => ['id' => '\d+']]));
    }

    /**
     * Failed before the L29 fix: the x flag of the compiled expression dropped the unescaped
     * space, so "/a b/:id" matched "/ab/1". Paths are matched as given: the caller decodes them
     * first ("/a%20b/1" is not "/a b/1").
     *
     * @see PLAN-port-php82.md L29
     */
    public function test_compile_space_in_text_rejects_path_without_it(): void
    {
        self::assertNull(self::match('/a b/:id', '/ab/1'));
    }

    /**
     * Failed before the L29 fix (the space was dropped from the expression).
     *
     * @see PLAN-port-php82.md L29
     */
    public function test_compile_space_in_text_matches_path_with_it(): void
    {
        self::assertSame(['id' => '1'], self::match('/a b/:id', '/a b/1'));
    }

    /**
     * Failed before the L29 fix (the space was dropped from the expression).
     *
     * @see PLAN-port-php82.md L29
     */
    public function test_compile_space_as_variable_separator_is_required_before_variable(): void
    {
        self::assertSame(['name' => 'x', 'id' => '5'], self::match('/:name :id', '/x 5'));
        self::assertNull(self::match('/:name :id', '/x5'));
    }

    /**
     * Upstream issue #109.
     */
    public function test_compile_variable_with_catch_all_require_captures_slashes(): void
    {
        self::assertSame(['filters' => 'a/b/c'], self::match('/search/:filters', '/search/a/b/c', ['require' => ['filters' => '.+']]));
    }

    /**
     * Upstream issue #109: "+" after a variable name is literal text, not a repetition modifier.
     */
    public function test_compile_plus_after_variable_name_is_literal_text(): void
    {
        self::assertSame(['filters' => 'a/b'], self::match('/search/:filters+', '/search/a/b+', ['require' => ['filters' => '.+']]));
        self::assertNull(self::match('/search/:filters+', '/search/a/b', ['require' => ['filters' => '.+']]));
    }
}
