<?php

namespace Pux\Tests\Export;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pux\Mux;

/**
 * Mux::export() produces PHP code; evaluating it yields a Mux (through Mux::__set_state) that
 * dispatches the same paths as the original.
 */
final class ExportRoundTripTest extends TestCase
{
    private static function roundTrip(Mux $mux): Mux
    {
        $copy = eval('return ' . $mux->export() . ';');
        self::assertInstanceOf(Mux::class, $copy);

        return $copy;
    }

    public function test_export_then_eval_yields_mux_dispatching_static_route(): void
    {
        $mux = new Mux();
        $mux->add('/product/:id', ['ProductController', 'itemAction']);
        $mux->add('/product', ['ProductController', 'listAction']);

        self::assertSame(
            [false, '/product', ['ProductController', 'listAction'], []],
            self::roundTrip($mux)->dispatch('/product'),
        );
    }

    public function test_export_then_eval_yields_mux_dispatching_pcre_route_with_variable(): void
    {
        $mux = new Mux();
        $mux->add('/product/:id', ['ProductController', 'itemAction']);
        $mux->add('/product', ['ProductController', 'listAction']);

        $route = self::roundTrip($mux)->dispatch('/product/10');

        self::assertSame('/product/:id', $route[3]['pattern']);
        self::assertSame('10', $route[3]['vars']['id']);
    }

    public function test_set_state_with_null_id_yields_mux_dispatching_given_routes(): void
    {
        $mux = Mux::__set_state([
            'id' => null,
            'routes' => [[false, '/hello', ['HelloController', 'show'], []]],
            'submux' => [],
            'staticRoutes' => [],
            'routesById' => [],
            'expand' => true,
        ]);

        self::assertSame([false, '/hello', ['HelloController', 'show'], []], $mux->dispatch('/hello'));
    }

    /**
     * Fails today with the warning "var_export does not handle circular references": mount()
     * stores the submux object, whose parent is the exporting Mux, in the route options.
     *
     * @see PLAN-port-php82.md L10
     */
    #[Group('known-defect')]
    public function test_export_after_mount_yields_mux_dispatching_mounted_route_without_warning(): void
    {
        $sub = new Mux();
        $sub->any('/bar', ['HelloController', 'show']);
        $mux = new Mux();
        $mux->mount('/foo', $sub);

        self::assertSame('/foo/bar', self::roundTrip($mux)->dispatch('/foo/bar')[1]);
    }
}
