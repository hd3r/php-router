<?php

declare(strict_types=1);

namespace Hd3r\Router;

use Hd3r\Router\Exception\RouterException;
use Hd3r\Router\Middleware\MiddlewareHandler;
use Hd3r\Router\Middleware\RouteHandler;
use Hd3r\Router\Traits\HasHooks;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 RequestHandler that dispatches requests to routes.
 *
 * Kept slim (~200 LOC) by delegating to specialized classes.
 */
class RouteDispatcher implements RequestHandlerInterface
{
    use HasHooks;

    private Dispatcher $dispatcher;
    private string $basePath;
    private string $trailingSlash;
    private bool $debug;

    /**
     * Create a new RouteDispatcher.
     *
     * @param array{0: array<string, array<string, Route>>, 1: array<string, array<int, array{regex: string, route: Route, casts: array<string, string>}>>} $dispatchData Compiled route data from RouteCollector
     * @param ContainerInterface|null $container PSR-11 container for dependency injection
     * @param string $basePath Base path prefix
     * @param string $trailingSlash Trailing slash mode ('strict' or 'ignore')
     * @param bool $debug Enable debug mode
     */
    public function __construct(
        array $dispatchData,
        private readonly ?ContainerInterface $container = null,
        string $basePath = '',
        string $trailingSlash = 'strict',
        bool $debug = false
    ) {
        $this->dispatcher = new Dispatcher($dispatchData[0], $dispatchData[1]);
        $this->basePath = $basePath;
        $this->trailingSlash = $trailingSlash;
        $this->debug = $debug;
    }

    /**
     * PSR-15: Handle a request and return a response.
     *
     * @param ServerRequestInterface $request PSR-7 request
     *
     * @return ResponseInterface PSR-7 response
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $uri = rawurldecode($request->getUri()->getPath());

        // BasePath handling: requests MUST start with basePath
        if ($this->basePath !== '') {
            $basePathLen = strlen($this->basePath);
            // Must start with basePath AND either be exact match or followed by '/'
            // This prevents /api from matching /apiX
            if (!str_starts_with($uri, $this->basePath) ||
                (strlen($uri) > $basePathLen && $uri[$basePathLen] !== '/')) {
                // Request doesn't have required basePath prefix -> 404
                return $this->handleNotFound($method, $uri);
            }
            $uri = substr($uri, $basePathLen) ?: '/';
        }

        // Trailing slash handling
        if ($this->trailingSlash === 'ignore' && $uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        $startTime = microtime(true);
        $match = $this->dispatcher->dispatch($method, $uri);

        try {
            return match ($match[0]) {
                Dispatcher::NOT_FOUND => $this->handleNotFound($method, $uri),
                Dispatcher::METHOD_NOT_ALLOWED => $this->handleMethodNotAllowed($method, $uri, $this->ensureStringArray($match[1])),
                Dispatcher::FOUND => $this->handleFound($this->ensureRoute($match[1]), $match[2], $match[3], $request, $method, $uri, $startTime),
                default => Response::serverError('Unknown dispatcher result'),
            };
        } catch (\TypeError $e) {
            // Casting errors (invalid int, float, bool) -> 400 Bad Request
            // This is a client error (invalid parameter), not a server error
            $this->trigger('error', [
                'method' => $method,
                'path' => $uri,
                'exception' => $e,
            ]);
            $message = $this->debug ? $e->getMessage() : 'Bad Request';
            return Response::error($message, 400, 'INVALID_PARAMETER');
        }
    }

    /**
     * Handle a matched route.
     *
     * @param Route $route Matched route
     * @param array<string, string> $params Route parameters
     * @param array<string, string> $casts Parameter type casts
     * @param ServerRequestInterface $request PSR-7 request
     * @param string $method HTTP method
     * @param string $uri Request URI
     * @param float $startTime Request start time for duration tracking
     *
     * @return ResponseInterface PSR-7 response
     */
    private function handleFound(
        Route $route,
        array $params,
        array $casts,
        ServerRequestInterface $request,
        string $method,
        string $uri,
        float $startTime
    ): ResponseInterface {
        // 1. Validated Type Casting (spec-compliant)
        /** @var array<string, string|int|float|bool> $castedParams */
        $castedParams = $params;
        foreach ($casts as $key => $type) {
            if (!isset($castedParams[$key])) {
                continue;
            }

            /** @var string $value */
            $value = $castedParams[$key];
            $castedParams[$key] = match ($type) {
                'int' => $this->castInt($value, $key),
                'float' => $this->castFloat($value, $key),
                'bool' => $this->castBool($value, $key),
                default => $value,
            };
        }

        // 2. Inject parameters into Request (BEFORE Middleware!)
        // Store route params separately for handler invocation
        $request = $request->withAttribute('_route_params', $castedParams);
        foreach ($castedParams as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        // 3. Build Middleware Chain
        $handler = new RouteHandler($route->handler, $this->container);
        foreach (array_reverse($route->middleware) as $middleware) {
            $handler = new MiddlewareHandler($this->resolveMiddleware($middleware), $handler);
        }

        $response = $handler->handle($request);

        // 4. Trigger hook AFTER successful dispatch
        $this->trigger('dispatch', [
            'method' => $method,
            'path' => $uri,
            'route' => $route->pattern,
            'handler' => $route->handler,
            'params' => $castedParams,
            'duration' => microtime(true) - $startTime,
        ]);

        return $response;
    }

    /**
     * Handle 404 Not Found.
     *
     * @param string $method HTTP method
     * @param string $uri Request URI
     *
     * @return ResponseInterface 404 response
     */
    private function handleNotFound(string $method, string $uri): ResponseInterface
    {
        $this->trigger('notFound', ['method' => $method, 'path' => $uri]);
        return Response::notFound();
    }

    /**
     * Handle 405 Method Not Allowed.
     *
     * @param string $method HTTP method
     * @param string $uri Request URI
     * @param string[] $allowed Allowed HTTP methods
     *
     * @return ResponseInterface 405 response
     */
    private function handleMethodNotAllowed(string $method, string $uri, array $allowed): ResponseInterface
    {
        $this->trigger('methodNotAllowed', [
            'method' => $method,
            'path' => $uri,
            'allowed_methods' => $allowed,
        ]);
        return Response::methodNotAllowed($allowed);
    }

    /**
     * Resolve middleware from class name or instance.
     *
     * @param string|object $middleware Middleware class name or instance
     *
     * @throws RouterException If middleware cannot be resolved
     *
     * @return MiddlewareInterface Resolved middleware
     */
    private function resolveMiddleware(string|object $middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if (is_string($middleware)) {
            if ($this->container?->has($middleware)) {
                $resolved = $this->container->get($middleware);
                if ($resolved instanceof MiddlewareInterface) {
                    return $resolved;
                }
            }
            if (class_exists($middleware)) {
                $instance = new $middleware();
                if ($instance instanceof MiddlewareInterface) {
                    return $instance;
                }
            }
        }

        throw new RouterException(
            sprintf("Cannot resolve middleware '%s'", is_string($middleware) ? $middleware : $middleware::class)
        );
    }

    // ==================== Validated Casting (Spec-compliant) ====================

    /**
     * Cast to int with strict validation.
     *
     * Rejects: 01, 1e3, 5.0, abc (only pure integers allowed)
     *
     * @param string $value Value to cast
     * @param string $key Parameter name for error messages
     *
     * @throws \TypeError If value is not a valid integer
     *
     * @return int Casted integer
     */
    private function castInt(string $value, string $key): int
    {
        // Accepts: 0, 5, -10. Rejects: 00, -0 (except literal 0), 01, 1e3, 5.0
        if (!preg_match('/^-?(?:0|[1-9]\d*)$/', $value)) {
            throw new \TypeError(
                sprintf("Parameter '%s': expected integer, got '%s'", $key, $value)
            );
        }

        $intVal = (int) $value;

        // Overflow check: casting back should give same string
        if ((string) $intVal !== $value) {
            throw new \TypeError(
                sprintf("Parameter '%s': integer overflow", $key)
            );
        }

        return $intVal;
    }

    /**
     * Cast to float with strict validation.
     *
     * Rejects: 1e3, trailing dot (5.)
     *
     * @param string $value Value to cast
     * @param string $key Parameter name for error messages
     *
     * @throws \TypeError If value is not a valid decimal
     *
     * @return float Casted float
     */
    private function castFloat(string $value, string $key): float
    {
        // Accepts: 5, 5.5, -3.14. Rejects: 1e3, 5.
        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new \TypeError(
                sprintf("Parameter '%s': expected decimal, got '%s'", $key, $value)
            );
        }

        return (float) $value;
    }

    /**
     * Cast to bool with strict validation.
     *
     * Only accepts: true, false, 1, 0
     *
     * @param string $value Value to cast
     * @param string $key Parameter name for error messages
     *
     * @throws \TypeError If value is not a valid boolean
     *
     * @return bool Casted boolean
     *
     * @codeCoverageIgnore Dead code: regex pattern filters invalid bool values before this is called
     */
    private function castBool(string $value, string $key): bool
    {
        return match (strtolower($value)) {
            'true', '1' => true,
            'false', '0' => false,
            default => throw new \TypeError(
                sprintf("Parameter '%s': expected boolean (true/false/1/0), got '%s'", $key, $value)
            ),
        };
    }

    // ==================== Type Assertion Helpers ====================

    /**
     * Ensure the value is a Route instance.
     *
     * @param mixed $value Value to check
     *
     * @return Route Validated Route instance
     */
    private function ensureRoute(mixed $value): Route
    {
        assert($value instanceof Route);
        return $value;
    }

    /**
     * Ensure the value is a string array (for allowed methods).
     *
     * @param mixed $value Value to check
     *
     * @return string[] Validated string array
     */
    private function ensureStringArray(mixed $value): array
    {
        assert(is_array($value));
        /** @var string[] $value */
        return $value;
    }
}
