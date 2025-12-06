<?php

declare(strict_types=1);

namespace Hd3r\Router\Tests\Feature;

use Hd3r\Router\RouteCollector;
use Hd3r\Router\RouteDispatcher;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class RedirectTest extends TestCase
{
    public function testSimpleRedirect(): void
    {
        $collector = new RouteCollector();
        $collector->redirect('/old', '/new');

        $dispatcher = new RouteDispatcher($collector->getData());

        $response = $dispatcher->handle(new ServerRequest('GET', '/old'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/new', $response->getHeaderLine('Location'));
    }

    public function testPermanentRedirect(): void
    {
        $collector = new RouteCollector();
        $collector->redirect('/old', '/new', 301);

        $dispatcher = new RouteDispatcher($collector->getData());

        $response = $dispatcher->handle(new ServerRequest('GET', '/old'));

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/new', $response->getHeaderLine('Location'));
    }

    public function testRedirectWithParameters(): void
    {
        $collector = new RouteCollector();
        $collector->redirect('/users/{id}/profile', '/profile/{id}');

        $dispatcher = new RouteDispatcher($collector->getData());

        $response = $dispatcher->handle(new ServerRequest('GET', '/users/42/profile'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/profile/42', $response->getHeaderLine('Location'));
    }

    public function testRedirectWorksWithHead(): void
    {
        $collector = new RouteCollector();
        $collector->redirect('/old', '/new');

        $dispatcher = new RouteDispatcher($collector->getData());

        $response = $dispatcher->handle(new ServerRequest('HEAD', '/old'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/new', $response->getHeaderLine('Location'));
    }

    public function testRedirectHandlerIsCacheFriendly(): void
    {
        $collector = new RouteCollector();
        $collector->redirect('/old', '/new');

        $routes = $collector->getRoutes();
        $handler = $routes[0]->handler;

        // Should not be a Closure
        $this->assertNotInstanceOf(\Closure::class, $handler);

        // Should be serializable (no Closures)
        $this->assertIsString(serialize($handler));
    }
}
