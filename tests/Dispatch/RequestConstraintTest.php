<?php

namespace Pux\Tests\Dispatch;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pux\Mux;
use Pux\RouteRequest;

/**
 * Route constraints on the request: method, domain (host) and secure (HTTPS). The request is
 * always passed as a RouteRequest; tests never write the server superglobal.
 *
 * Tests in group pending-stage-1 cannot run before Stage 1: RouteRequest extends the removed
 * Universal\Http\HttpRequest and cannot be loaded.
 */
final class RequestConstraintTest extends TestCase
{
    /**
     * @see PLAN-port-php82.md L4
     * @see Stage 1
     */
    #[Group('known-defect')]
    #[Group('pending-stage-1')]
    public function test_dispatch_request_with_post_method_returns_post_route(): void
    {
        $mux = new Mux();
        $mux->post('/p', ['PageController', 'page1']);

        $route = $mux->dispatchRequest(new RouteRequest('POST', '/p'));

        self::assertSame('/p', $route[1]);
    }

    /**
     * @see PLAN-port-php82.md L4
     * @see Stage 1
     */
    #[Group('known-defect')]
    #[Group('pending-stage-1')]
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
     * @see PLAN-port-php82.md L3
     * @see Stage 1
     */
    #[Group('known-defect')]
    #[Group('pending-stage-1')]
    public function test_dispatch_with_secure_route_rejects_plain_http(): void
    {
        $mux = new Mux();
        $mux->any('/s', ['PageController', 'page1'], ['secure' => true]);

        self::assertNull($mux->dispatch('/s', RouteRequest::create('GET', '/s', [])));
    }

    /**
     * @see PLAN-port-php82.md L3
     * @see Stage 1
     */
    #[Group('known-defect')]
    #[Group('pending-stage-1')]
    public function test_dispatch_with_secure_route_accepts_https(): void
    {
        $mux = new Mux();
        $mux->any('/s', ['PageController', 'page1'], ['secure' => true]);

        $route = $mux->dispatch('/s', RouteRequest::create('GET', '/s', ['HTTPS' => 'on']));

        self::assertSame('/s', $route[1]);
    }

    /**
     * @see PLAN-port-php82.md L11
     * @see Stage 1
     */
    #[Group('known-defect')]
    #[Group('pending-stage-1')]
    public function test_dispatch_with_domain_route_accepts_request_for_that_host(): void
    {
        $mux = new Mux();
        $mux->add('/foo', ['PageController', 'page1'], ['domain' => 'test.dev']);

        $route = $mux->dispatch('/foo', RouteRequest::create('GET', '/foo', ['HTTP_HOST' => 'test.dev']));

        self::assertSame('/foo', $route[1]);
    }

    /**
     * @see PLAN-port-php82.md L11
     * @see Stage 1
     */
    #[Group('known-defect')]
    #[Group('pending-stage-1')]
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
