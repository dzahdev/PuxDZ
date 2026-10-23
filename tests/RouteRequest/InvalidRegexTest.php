<?php

namespace Pux\Tests\RouteRequest;

use PHPUnit\Framework\TestCase;
use Pux\Exception\PatternSyntax;
use Pux\RouteRequest;

/**
 * A regex predicate given an expression that does not compile throws PatternSyntax naming the
 * expression, instead of answering "no match".
 *
 * Failed before the L28 fix: preg_match emitted a warning and the predicate returned false.
 *
 * @see PLAN-port-php82.md L28
 */
final class InvalidRegexTest extends TestCase
{
    public function test_path_match_with_invalid_regex_throws_pattern_syntax(): void
    {
        $this->expectException(PatternSyntax::class);
        $this->expectExceptionMessage('#^/item/[#');

        RouteRequest::create('GET', '/item/1')->pathMatch('#^/item/[#');
    }

    public function test_host_match_with_invalid_regex_throws_pattern_syntax(): void
    {
        $this->expectException(PatternSyntax::class);
        $this->expectExceptionMessage('#(api#');

        RouteRequest::create('GET', '/', ['HTTP_HOST' => 'api.example.com'])->hostMatch('#(api#');
    }

    public function test_query_string_match_with_invalid_regex_throws_pattern_syntax(): void
    {
        $this->expectException(PatternSyntax::class);
        $this->expectExceptionMessage('#page=[#');

        RouteRequest::create('GET', '/', ['QUERY_STRING' => 'page=2'])->queryStringMatch('#page=[#');
    }

    /**
     * Failed before the L30 fix: without the server key the predicate returned false before it
     * compiled the expression.
     *
     * @see PLAN-port-php82.md L30
     */
    public function test_host_match_without_host_and_with_invalid_regex_throws_pattern_syntax(): void
    {
        $this->expectException(PatternSyntax::class);
        $this->expectExceptionMessage('#(api#');

        RouteRequest::create('GET', '/')->hostMatch('#(api#');
    }

    /**
     * Failed before the L30 fix: without the server key the predicate returned false before it
     * compiled the expression.
     *
     * @see PLAN-port-php82.md L30
     */
    public function test_query_string_match_without_query_string_and_with_invalid_regex_throws_pattern_syntax(): void
    {
        $this->expectException(PatternSyntax::class);
        $this->expectExceptionMessage('#page=[#');

        RouteRequest::create('GET', '/')->queryStringMatch('#page=[#');
    }
}
