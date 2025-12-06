<?php

declare(strict_types=1);

namespace Hd3r\Router\Tests\Unit;

use Hd3r\Router\Middleware\RedirectHandler;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class RedirectHandlerTest extends TestCase
{
    public function testSimpleRedirect(): void
    {
        $handler = new RedirectHandler('/new-location');

        $response = $handler->handle(new ServerRequest('GET', '/old'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/new-location', $response->getHeaderLine('Location'));
    }

    public function testPermanentRedirect(): void
    {
        $handler = new RedirectHandler('/new-location', 301);

        $response = $handler->handle(new ServerRequest('GET', '/old'));

        $this->assertSame(301, $response->getStatusCode());
    }

    public function testRedirectWithParameterReplacement(): void
    {
        $handler = new RedirectHandler('/profile/{id}');

        $request = (new ServerRequest('GET', '/users/42/profile'))
            ->withAttribute('id', 42);

        $response = $handler->handle($request);

        $this->assertSame('/profile/42', $response->getHeaderLine('Location'));
    }

    public function testRedirectWithMultipleParameters(): void
    {
        $handler = new RedirectHandler('/posts/{year}/{slug}');

        $request = (new ServerRequest('GET', '/old'))
            ->withAttribute('year', 2025)
            ->withAttribute('slug', 'hello');

        $response = $handler->handle($request);

        $this->assertSame('/posts/2025/hello', $response->getHeaderLine('Location'));
    }

    public function testRedirectIgnoresNonScalarAttributes(): void
    {
        $handler = new RedirectHandler('/test/{id}');

        $request = (new ServerRequest('GET', '/old'))
            ->withAttribute('id', 42)
            ->withAttribute('object', new \stdClass());

        $response = $handler->handle($request);

        // Should only replace scalar values
        $this->assertSame('/test/42', $response->getHeaderLine('Location'));
    }

    public function testGetTarget(): void
    {
        $handler = new RedirectHandler('/target-url');

        $this->assertSame('/target-url', $handler->getTarget());
    }

    public function testGetStatus(): void
    {
        $handler301 = new RedirectHandler('/url', 301);
        $handler302 = new RedirectHandler('/url', 302);
        $handler307 = new RedirectHandler('/url', 307);

        $this->assertSame(301, $handler301->getStatus());
        $this->assertSame(302, $handler302->getStatus());
        $this->assertSame(307, $handler307->getStatus());
    }

    public function testIsSerializable(): void
    {
        $handler = new RedirectHandler('/new', 301);

        $serialized = serialize($handler);
        $unserialized = unserialize($serialized);

        $this->assertSame('/new', $unserialized->getTarget());
        $this->assertSame(301, $unserialized->getStatus());
    }

    public function testSetState(): void
    {
        // Test __set_state for var_export() cache support
        $original = new RedirectHandler('/cached', 301);

        // Simulate what var_export does
        $exported = var_export($original, true);
        $restored = eval('return ' . $exported . ';');

        $this->assertInstanceOf(RedirectHandler::class, $restored);
        $this->assertSame('/cached', $restored->getTarget());
        $this->assertSame(301, $restored->getStatus());
    }
}
