<?php

namespace Pux\Tests\Dispatch;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pux\Mux;
use Pux\RouteRequest;

/**
 * Route constraints on the request: method, domain (host) and secure (HTTPS). The request is
 * always passed as a RouteRequest; tests never write the server superglobal.
 */
final class RequestConstraintTest extends TestCase
{
    /**
     * Failed before the L4 fix: dispatch() did not pass the request to match(), which read the
     * method from the server superglobal.
     *
     * @see PLAN-port-php82.md L4
     */
    public function test_dispatch_request_with_post_method_returns_post_route(): void
    {
        $mux = new Mux();
        $mux->post('/p', ['PageController', 'page1']);

        $route = $mux->dispatchRequest(new RouteRequest('POST', '/p'));

        self::assertSame('/p', $route[1]);
    }

    /**
     * Failed before the L4 fix: dispatch() did not pass the request to match(), which read the
     * method from the server superglobal.
     *
     * @see PLAN-port-php82.md L4
     */
    public function test_dispatch_with_get_request_returns_get_routes(): void
    {
        $mux = new Mux();
        $mux->get('/news', ['NewsController', 'listAction']);
        $mux->get('/news_item', ['NewsController', 'itemAction'], []);
        $request = new RouteRequest('GET', '/news');

        self::assertSame(['NewsController', 'itemAction'], $mux->dispatch('/news_item', $request)[2]);
        self::assertSame(['NewsController', 'listAction'], $mux->dispatch('/news', $request)[2]);
    }

    /**
     * Without a RouteRequest argument, dispatch builds one with RouteRequest::fromServer() when
     * it reaches a route with a request constraint. PHPUnit runs in the CLI, where the server
     * superglobal has no REQUEST_METHOD, so fromServer throws. Failed before the L4 fix: the
     * constraint was compared with null and the route was skipped.
     *
     * @see PLAN-port-php82.md L4
     */
    public function test_dispatch_without_request_to_route_with_method_constraint_outside_web_server_throws(): void
    {
        $mux = new Mux();
        $mux->get('/g', ['PageController', 'page1']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('REQUEST_METHOD');

        $mux->dispatch('/g');
    }

    /**
     * @see PLAN-port-php82.md L4
     */
    public function test_dispatch_without_request_to_route_without_constraint_returns_route(): void
    {
        $mux = new Mux();
        $mux->get('/g', ['PageController', 'page1']);
        $mux->any('/a', ['PageController', 'page2']);

        self::assertSame('/a', $mux->dispatch('/a')[1]);
    }

    /**
     * @see PLAN-port-php82.md L3
     */
    #[Group('known-defect')]
    public function test_dispatch_with_secure_route_rejects_plain_http(): void
    {
        $mux = new Mux();
        $mux->any('/s', ['PageController', 'page1'], ['secure' => true]);

        self::assertNull($mux->dispatch('/s', RouteRequest::create('GET', '/s', [])));
    }

    /**
     * @see PLAN-port-php82.md L3
     */
    #[Group('known-defect')]
    public function test_dispatch_with_secure_route_accepts_https(): void
    {
        $mux = new Mux();
        $mux->any('/s', ['PageController', 'page1'], ['secure' => true]);

        $route = $mux->dispatch('/s', RouteRequest::create('GET', '/s', ['HTTPS' => 'on']));

        self::assertSame('/s', $route[1]);
    }

    /**
     * @see PLAN-port-php82.md L11
     */
    #[Group('known-defect')]
    public function test_dispatch_with_domain_route_accepts_request_for_that_host(): void
    {
        $mux = new Mux();
        $mux->add('/foo', ['PageController', 'page1'], ['domain' => 'test.dev']);

        $route = $mux->dispatch('/foo', RouteRequest::create('GET', '/foo', ['HTTP_HOST' => 'test.dev']));

        self::assertSame('/foo', $route[1]);
    }

    /**
     * @see PLAN-port-php82.md L11
     */
    #[Group('known-defect')]
    public function test_dispatch_with_domain_route_rejects_request_for_other_host(): void
    {
        $mux = new Mux();
        $mux->add('/foo', ['PageController', 'page1'], ['domain' => 'test.dev']);

        self::assertNull($mux->dispatch('/foo', RouteRequest::create('GET', '/foo', ['HTTP_HOST' => 'github.com'])));
    }

    /**
     * Fails today with the warning "Undefined array key HTTP_HOST" (Mux::match reads the server superglobal).
     *
     * @see PLAN-port-php82.md L11
     */
    #[Group('known-defect')]
    public function test_dispatch_with_domain_route_and_no_host_returns_null_without_warning(): void
    {
        $mux = new Mux();
        $mux->any('/d', ['PageController', 'page1'], ['domain' => 'a.com']);

        self::assertNull($mux->dispatch('/d'));
    }
}
