<?php

declare(strict_types=1);

namespace Pux\Tests\Url;

use PHPUnit\Framework\TestCase;
use Pux\Mux;

/**
 * Mux::url builds a path from a route registered with the 'id' option.
 */
final class UrlTest extends TestCase
{
    private static function blogMux(): Mux
    {
        $mux = new Mux();
        $mux->any('/blog/:year/:month(/:slug)', ['PageController', 'page1'], ['id' => 'post']);

        return $mux;
    }

    public function test_url_replaces_variables_and_drops_omitted_optional_segment(): void
    {
        self::assertSame('/blog/2024/5', self::blogMux()->url('post', ['year' => 2024, 'month' => 5]));
    }

    public function test_url_fills_optional_segment_when_its_variable_is_given(): void
    {
        self::assertSame(
            '/blog/2024/5/x',
            self::blogMux()->url('post', ['year' => 2024, 'month' => 5, 'slug' => 'x']),
        );
    }

    public function test_url_replaces_single_variable_of_named_route(): void
    {
        $mux = new Mux();
        $mux->add('/hello/:name', ['HelloController', 'index'], ['id' => 'hello-name']);
        $mux->add('/foo', ['HelloController', 'show'], ['id' => 'foo']);

        self::assertSame('/hello/john', $mux->url('hello-name', ['name' => 'john']));
    }

    /**
     * Failed before the L24 fix with the warning "Undefined array key pattern" and the result ''.
     *
     * @see PLAN-port-php82.md L24
     */
    public function test_url_of_static_named_route_returns_its_path(): void
    {
        $mux = new Mux();
        $mux->add('/foo', ['HelloController', 'show'], ['id' => 'foo']);

        self::assertSame('/foo', $mux->url('foo'));
    }

    /**
     * @see PLAN-port-php82.md L24
     */
    public function test_url_of_unknown_route_id_throws_naming_it(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing');

        (new Mux())->url('missing');
    }

    /**
     * Values are inserted as given; encoding them is the caller's job.
     *
     * @see PLAN-port-php82.md L24
     */
    public function test_url_inserts_values_without_encoding(): void
    {
        $mux = new Mux();
        $mux->add('/hello/:name', ['HelloController', 'index'], ['id' => 'hello-name']);

        self::assertSame('/hello/a b', $mux->url('hello-name', ['name' => 'a b']));
    }
}
