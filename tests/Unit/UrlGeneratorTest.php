<?php

declare(strict_types=1);

namespace Hd3r\Router\Tests\Unit;

use Hd3r\Router\Exception\RouteNotFoundException;
use Hd3r\Router\Exception\RouterException;
use Hd3r\Router\Route;
use Hd3r\Router\UrlGenerator;
use PHPUnit\Framework\TestCase;

class UrlGeneratorTest extends TestCase
{
    public function testGenerateSimpleUrl(): void
    {
        $routes = [
            new Route(['GET'], '/users', 'handler', [], 'users.index'),
        ];
        $generator = new UrlGenerator($routes);

        $url = $generator->url('users.index');
        $this->assertSame('/users', $url);
    }

    public function testGenerateUrlWithParameter(): void
    {
        $routes = [
            new Route(['GET'], '/users/{id}', 'handler', [], 'users.show'),
        ];
        $generator = new UrlGenerator($routes);

        $url = $generator->url('users.show', ['id' => 42]);
        $this->assertSame('/users/42', $url);
    }

    public function testGenerateUrlWithMultipleParameters(): void
    {
        $routes = [
            new Route(['GET'], '/posts/{year}/{slug}', 'handler', [], 'posts.show'),
        ];
        $generator = new UrlGenerator($routes);

        $url = $generator->url('posts.show', ['year' => 2025, 'slug' => 'hello-world']);
        $this->assertSame('/posts/2025/hello-world', $url);
    }

    public function testGenerateUrlWithConstraint(): void
    {
        $routes = [
            new Route(['GET'], '/users/{id:int}', 'handler', [], 'users.show'),
        ];
        $generator = new UrlGenerator($routes);

        $url = $generator->url('users.show', ['id' => 42]);
        $this->assertSame('/users/42', $url);
    }

    public function testGenerateUrlWithBasePath(): void
    {
        $routes = [
            new Route(['GET'], '/users', 'handler', [], 'users.index'),
        ];
        $generator = new UrlGenerator($routes);
        $generator->setBasePath('/api/v1');

        $url = $generator->url('users.index');
        $this->assertSame('/api/v1/users', $url);
    }

    public function testGenerateAbsoluteUrl(): void
    {
        $routes = [
            new Route(['GET'], '/users/{id}', 'handler', [], 'users.show'),
        ];
        $generator = new UrlGenerator($routes);
        $generator->setBaseUrl('https://api.example.com');

        $url = $generator->absoluteUrl('users.show', ['id' => 42]);
        $this->assertSame('https://api.example.com/users/42', $url);
    }

    public function testGenerateAbsoluteUrlWithBasePath(): void
    {
        $routes = [
            new Route(['GET'], '/users', 'handler', [], 'users.index'),
        ];
        $generator = new UrlGenerator($routes);
        $generator->setBasePath('/api');
        $generator->setBaseUrl('https://example.com');

        $url = $generator->absoluteUrl('users.index');
        $this->assertSame('https://example.com/api/users', $url);
    }

    public function testAbsoluteUrlThrowsWithoutBaseUrl(): void
    {
        $routes = [
            new Route(['GET'], '/users', 'handler', [], 'users.index'),
        ];
        $generator = new UrlGenerator($routes);

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('baseUrl is not configured');
        $generator->absoluteUrl('users.index');
    }

    public function testThrowsOnUnknownRoute(): void
    {
        $generator = new UrlGenerator([]);

        $this->expectException(RouteNotFoundException::class);
        $generator->url('nonexistent.route');
    }

    public function testThrowsOnMissingParameter(): void
    {
        $routes = [
            new Route(['GET'], '/users/{id}', 'handler', [], 'users.show'),
        ];
        $generator = new UrlGenerator($routes);

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Missing parameter "id"');
        $generator->url('users.show', []);
    }

    public function testHasRoute(): void
    {
        $routes = [
            new Route(['GET'], '/users', 'handler', [], 'users.index'),
        ];
        $generator = new UrlGenerator($routes);

        $this->assertTrue($generator->hasRoute('users.index'));
        $this->assertFalse($generator->hasRoute('nonexistent'));
    }

    public function testIgnoresRoutesWithoutName(): void
    {
        $routes = [
            new Route(['GET'], '/users', 'handler'), // No name
        ];
        $generator = new UrlGenerator($routes);

        $this->assertFalse($generator->hasRoute(''));
    }

    public function testHandlesOptionalSegments(): void
    {
        $routes = [
            new Route(['GET'], '/users[/{id}]', 'handler', [], 'users.optional'),
        ];
        $generator = new UrlGenerator($routes);

        // Without param - brackets are stripped
        $url = $generator->url('users.optional', ['id' => 5]);
        $this->assertSame('/users/5', $url);
    }

    public function testSetBaseUrlTrimsTrailingSlash(): void
    {
        $routes = [
            new Route(['GET'], '/test', 'handler', [], 'test'),
        ];
        $generator = new UrlGenerator($routes);
        $generator->setBaseUrl('https://example.com/');

        $url = $generator->absoluteUrl('test');
        $this->assertSame('https://example.com/test', $url);
    }

    public function testSetBasePathTrimsTrailingSlash(): void
    {
        $routes = [
            new Route(['GET'], '/test', 'handler', [], 'test'),
        ];
        $generator = new UrlGenerator($routes);
        $generator->setBasePath('/api/');

        $url = $generator->url('test');
        $this->assertSame('/api/test', $url);
    }

    public function testSetBaseUrlToNull(): void
    {
        $routes = [
            new Route(['GET'], '/test', 'handler', [], 'test'),
        ];
        $generator = new UrlGenerator($routes);
        $generator->setBaseUrl('https://example.com');
        $generator->setBaseUrl(null);

        $this->expectException(RouterException::class);
        $generator->absoluteUrl('test');
    }

    public function testConstructorWithPatternMapping(): void
    {
        // This is the format used when loading from cache (name => pattern)
        $patternMapping = [
            'users.index' => '/users',
            'users.show' => '/users/{id}',
            'posts.show' => '/posts/{year}/{slug}',
        ];

        $generator = new UrlGenerator($patternMapping);

        $this->assertTrue($generator->hasRoute('users.index'));
        $this->assertTrue($generator->hasRoute('users.show'));
        $this->assertTrue($generator->hasRoute('posts.show'));

        $this->assertSame('/users', $generator->url('users.index'));
        $this->assertSame('/users/42', $generator->url('users.show', ['id' => 42]));
        $this->assertSame('/posts/2025/hello', $generator->url('posts.show', ['year' => 2025, 'slug' => 'hello']));
    }
}
