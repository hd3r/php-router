<?php

declare(strict_types=1);

namespace Hd3r\Router\Middleware;

use Hd3r\Router\Exception\RouterException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Final handler that executes the route controller/callable.
 *
 * IMPORTANT: Controller MUST return ResponseInterface (no array magic!).
 */
class RouteHandler implements RequestHandlerInterface
{
    /**
     * Create a new RouteHandler instance.
     *
     * @param mixed $handler Controller class, callable, or RequestHandler
     * @param ContainerInterface|null $container PSR-11 container for dependency injection
     */
    public function __construct(
        private readonly mixed $handler,
        private readonly ?ContainerInterface $container = null
    ) {
    }

    /**
     * Handle the request by executing the route handler.
     *
     * @param ServerRequestInterface $request PSR-7 request
     *
     * @throws RouterException If handler is invalid or does not return ResponseInterface
     *
     * @return ResponseInterface PSR-7 response
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Use only route params (not all attributes, which may include middleware-added ones)
        $arguments = $request->getAttribute('_route_params', []);

        // PSR-15 RequestHandler (e.g., RedirectHandler)
        if ($this->handler instanceof RequestHandlerInterface) {
            return $this->handler->handle($request);
        }

        // Controller class + method
        if (is_array($this->handler) && count($this->handler) === 2) {
            [$class, $method] = $this->handler;

            // Resolve from container or instantiate directly
            /** @var class-string $class */
            $instance = ($this->container?->has($class))
                ? $this->container->get($class)
                : new $class();

            // PHP 8 Named Arguments: ['id' => 5] becomes id: 5
            $response = $instance->{$method}($request, ...$arguments);

        } elseif (is_callable($this->handler)) {
            // Closure or callable
            $response = ($this->handler)($request, ...$arguments);

        } else {
            throw new RouterException('Invalid route handler.');
        }

        // NO ARRAY MAGIC! Controller MUST use Response::success() etc.
        if (!$response instanceof ResponseInterface) {
            throw new RouterException(
                sprintf(
                    'Handler must return ResponseInterface, got %s. Use Response::success($data).',
                    get_debug_type($response)
                )
            );
        }

        return $response;
    }
}
