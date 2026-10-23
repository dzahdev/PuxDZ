<?php

namespace Pux\Tests\Dispatch;

use PHPUnit\Framework\TestCase;
use Pux\Mux;

/**
 * A string callback naming a class and a method is stored as [class, method]: both the
 * 'Class:method' form of 2.x and the 'Class::method' form of PHP callables.
 */
final class StringCallbackTest extends TestCase
{
    public function test_dispatch_route_added_with_single_colon_callback_returns_class_and_method(): void
    {
        $mux = new Mux();
        $mux->add('/product', 'ProductController:listAction');

        self::assertSame(['ProductController', 'listAction'], $mux->dispatch('/product')[2]);
    }

    /**
     * Failed before the L35 fix with ['ProductController', '', 'listAction']: the callback was
     * split on every ':'.
     *
     * @see PLAN-port-php82.md L35
     */
    public function test_dispatch_route_added_with_double_colon_callback_returns_class_and_method(): void
    {
        $mux = new Mux();
        $mux->add('/product', 'ProductController::listAction');

        self::assertSame(['ProductController', 'listAction'], $mux->dispatch('/product')[2]);
    }

    /**
     * @see PLAN-port-php82.md L35
     */
    public function test_dispatch_pcre_route_added_with_namespaced_double_colon_callback_returns_class_and_method(): void
    {
        $mux = new Mux();
        $mux->add('/product/:id', 'Shop\\ProductController::itemAction');

        self::assertSame(['Shop\\ProductController', 'itemAction'], $mux->dispatch('/product/7')[2]);
    }
}
