<?php

declare(strict_types=1);

namespace Hd3r\Router\Tests\Unit;

use Hd3r\Router\Exception\RouterException;
use Hd3r\Router\Response;
use Hd3r\Router\Route;
use Hd3r\Router\RouteDispatcher;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RouteDispatcherTest extends TestCase
{
    public function testMiddlewareFromContainer(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return Response::success(['from_container' => true]);
            }
        };

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('TestMiddleware')->willReturn(true);
        $container->method('get')->with('TestMiddleware')->willReturn($middleware);

        $route = new Route(['GET'], '/test', fn($req) => Response::success(['ok' => true]));
        $route->middleware('TestMiddleware');

        $dispatcher = new RouteDispatcher(
            [['GET' => ['/test' => $route]], []],
            $container
        );

        $response = $dispatcher->handle(new ServerRequest('GET', '/test'));

        $body = json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['data']['from_container']);
    }

    public function testMiddlewareAsClassString(): void
    {
        $route = new Route(['GET'], '/test', fn($req) => Response::success(['ok' => true]));
        $route->middleware(TestMiddlewareClass::class);

        $dispatcher = new RouteDispatcher(
            [['GET' => ['/test' => $route]], []],
            null
        );

        $response = $dispatcher->handle(new ServerRequest('GET', '/test'));

        $body = json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['data']['class_instantiated']);
    }

    public function testInvalidMiddlewareThrows(): void
    {
        $route = new Route(['GET'], '/test', fn($req) => Response::success([]));
        $route->middleware('NonExistentMiddleware');

        $dispatcher = new RouteDispatcher(
            [['GET' => ['/test' => $route]], []],
            null
        );

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage("Cannot resolve middleware");
        $dispatcher->handle(new ServerRequest('GET', '/test'));
    }

    public function testMiddlewareAsObject(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return Response::success(['object_middleware' => true]);
            }
        };

        $route = new Route(['GET'], '/test', fn($req) => Response::success(['ok' => true]));
        $route->middleware($middleware);

        $dispatcher = new RouteDispatcher(
            [['GET' => ['/test' => $route]], []],
            null
        );

        $response = $dispatcher->handle(new ServerRequest('GET', '/test'));

        $body = json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['data']['object_middleware']);
    }

    public function testTrailingSlashStrict(): void
    {
        $route = new Route(['GET'], '/test', fn($req) => Response::success(['ok' => true]));

        $dispatcher = new RouteDispatcher(
            [['GET' => ['/test' => $route]], []],
            null,
            '',
            'strict'
        );

        // Without trailing slash - matches
        $response1 = $dispatcher->handle(new ServerRequest('GET', '/test'));
        $this->assertSame(200, $response1->getStatusCode());

        // With trailing slash - no match (strict)
        $response2 = $dispatcher->handle(new ServerRequest('GET', '/test/'));
        $this->assertSame(404, $response2->getStatusCode());
    }

    public function testTrailingSlashIgnore(): void
    {
        $route = new Route(['GET'], '/test', fn($req) => Response::success(['ok' => true]));

        $dispatcher = new RouteDispatcher(
            [['GET' => ['/test' => $route]], []],
            null,
            '',
            'ignore'
        );

        // Both should match
        $response1 = $dispatcher->handle(new ServerRequest('GET', '/test'));
        $this->assertSame(200, $response1->getStatusCode());

        $response2 = $dispatcher->handle(new ServerRequest('GET', '/test/'));
        $this->assertSame(200, $response2->getStatusCode());
    }

    public function testCastingParamNotInParams(): void
    {
        // Edge case: cast defined but param not present
        $route = new Route(['GET'], '/test/{id:int}', fn($req, int $id) => Response::success(['id' => $id]));

        $dispatcher = new RouteDispatcher(
            [
                [],
                ['GET' => [
                    [
                        'regex' => '#^/test/(?P<id>\d+)$#',
                        'route' => $route,
                        'casts' => ['id' => 'int', 'missing' => 'int'], // 'missing' param doesn't exist
                    ],
                ]],
            ],
            null
        );

        $response = $dispatcher->handle(new ServerRequest('GET', '/test/42'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDefaultCastType(): void
    {
        // Test unknown cast type falls through to default
        $route = new Route(['GET'], '/test/{id}', fn($req, $id) => Response::success(['id' => $id]));

        $dispatcher = new RouteDispatcher(
            [
                [],
                ['GET' => [
                    [
                        'regex' => '#^/test/(?P<id>[^/]+)$#',
                        'route' => $route,
                        'casts' => ['id' => 'unknown'], // Unknown type
                    ],
                ]],
            ],
            null
        );

        $response = $dispatcher->handle(new ServerRequest('GET', '/test/value'));
        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('value', $body['data']['id']); // Not cast, kept as string
    }

    public function testCastFloatValid(): void
    {
        $route = new Route(['GET'], '/test/{price:float}', fn($req, float $price) => Response::success(['price' => $price]));

        $dispatcher = new RouteDispatcher(
            [
                [],
                ['GET' => [
                    [
                        'regex' => '#^/test/(?P<price>-?\d+(?:\.\d+)?)$#',
                        'route' => $route,
                        'casts' => ['price' => 'float'],
                    ],
                ]],
            ],
            null
        );

        $response = $dispatcher->handle(new ServerRequest('GET', '/test/3.14'));
        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(3.14, $body['data']['price']);
    }

    public function testCastFloatRejectsScientificNotation(): void
    {
        $route = new Route(['GET'], '/test/{val:float}', fn($req, float $val) => Response::success(['val' => $val]));

        // With debug=true, detailed error message is shown
        $dispatcher = new RouteDispatcher(
            [
                [],
                ['GET' => [
                    [
                        'regex' => '#^/test/(?P<val>[^/]+)$#', // Permissive regex to let invalid value through
                        'route' => $route,
                        'casts' => ['val' => 'float'],
                    ],
                ]],
            ],
            null,
            '',
            'strict',
            true // debug=true
        );

        $response = $dispatcher->handle(new ServerRequest('GET', '/test/1e3'));

        // TypeError is caught and converted to 500 response with debug info
        $this->assertSame(500, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('expected decimal', $body['message']);
    }

    public function testTypeErrorHidesDetailsInProduction(): void
    {
        $route = new Route(['GET'], '/test/{val:float}', fn($req, float $val) => Response::success(['val' => $val]));

        // With debug=false (production), generic error message is shown
        $dispatcher = new RouteDispatcher(
            [
                [],
                ['GET' => [
                    [
                        'regex' => '#^/test/(?P<val>[^/]+)$#',
                        'route' => $route,
                        'casts' => ['val' => 'float'],
                    ],
                ]],
            ],
            null,
            '',
            'strict',
            false // debug=false (production)
        );

        $response = $dispatcher->handle(new ServerRequest('GET', '/test/1e3'));

        $this->assertSame(500, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        // Should NOT contain internal details
        $this->assertStringNotContainsString('expected decimal', $body['message']);
        $this->assertSame('Internal Server Error', $body['message']);
    }

    public function testHooksTriggered(): void
    {
        $route = new Route(['GET'], '/test', fn($req) => Response::success([]));

        $dispatcher = new RouteDispatcher(
            [['GET' => ['/test' => $route]], []],
            null
        );

        $dispatchData = null;
        $dispatcher->on('dispatch', function ($data) use (&$dispatchData) {
            $dispatchData = $data;
        });

        $dispatcher->handle(new ServerRequest('GET', '/test'));

        $this->assertNotNull($dispatchData);
        $this->assertSame('GET', $dispatchData['method']);
        $this->assertSame('/test', $dispatchData['path']);
    }

    public function testTypeErrorCaught(): void
    {
        // Create a handler that causes TypeError during casting
        $route = new Route(['GET'], '/test/{id:int}', fn($req, int $id) => Response::success(['id' => $id]));

        $dispatcher = new RouteDispatcher(
            [
                [],
                ['GET' => [
                    [
                        'regex' => '#^/test/(?P<id>[^/]+)$#', // Accepts anything
                        'route' => $route,
                        'casts' => ['id' => 'int'],
                    ],
                ]],
            ],
            null
        );

        // "abc" will cause TypeError when casting to int
        $response = $dispatcher->handle(new ServerRequest('GET', '/test/abc'));
        $this->assertSame(500, $response->getStatusCode());
    }
}

class TestMiddlewareClass implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return Response::success(['class_instantiated' => true]);
    }
}
