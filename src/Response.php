<?php

declare(strict_types=1);

namespace Hd3r\Router;

use Nyholm\Psr7\Response as Psr7Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Factory for standardized API responses.
 *
 * JSON Structure:
 * Success: {"success": true, "data": {...}, "message": "...", "meta": {...}}
 * Error: {"success": false, "message": "...", "error": {"message": "...", "code": "...", "details": {...}}}
 */
final class Response
{
    // ==================== Success Responses ====================

    /**
     * 200 OK response.
     *
     * @param mixed $data Response data
     * @param string|null $message Optional success message
     * @param array<string, mixed>|null $meta Optional metadata
     * @return ResponseInterface
     */
    public static function success(
        mixed $data,
        ?string $message = null,
        ?array $meta = null,
    ): ResponseInterface {
        return self::json(200, self::successBody($data, $message, $meta));
    }

    /**
     * 201 Created response.
     *
     * @param mixed $data Created resource data
     * @param string|null $message Optional success message
     * @param string|null $location Optional Location header URL
     * @return ResponseInterface
     */
    public static function created(
        mixed $data,
        ?string $message = null,
        ?string $location = null,
    ): ResponseInterface {
        $response = self::json(201, self::successBody($data, $message));

        if ($location !== null) {
            $response = $response->withHeader('Location', $location);
        }

        return $response;
    }

    /**
     * 202 Accepted response.
     *
     * @param mixed $data Response data
     * @param string|null $message Optional success message
     * @return ResponseInterface
     */
    public static function accepted(mixed $data, ?string $message = null): ResponseInterface
    {
        return self::json(202, self::successBody($data, $message));
    }

    /**
     * 204 No Content response.
     *
     * @return ResponseInterface
     */
    public static function noContent(): ResponseInterface
    {
        return new Psr7Response(204);
    }

    /**
     * 200 OK response with pagination meta.
     *
     * @param array<mixed> $items Paginated items
     * @param int $total Total number of items
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @return ResponseInterface
     */
    public static function paginated(
        array $items,
        int $total,
        int $page,
        int $perPage,
    ): ResponseInterface {
        $lastPage = (int) ceil($total / $perPage);

        return self::json(200, self::successBody($items, null, [
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total),
            ],
        ]));
    }

    // ==================== Error Responses ====================

    /**
     * Generic error response.
     *
     * @param string $message Error message
     * @param int $status HTTP status code (default: 400)
     * @param string|null $code Error code (e.g., 'INVALID_INPUT')
     * @param array<string, mixed>|null $details Additional error details
     * @return ResponseInterface
     */
    public static function error(
        string $message,
        int $status = 400,
        ?string $code = null,
        ?array $details = null,
    ): ResponseInterface {
        return self::json($status, self::errorBody($message, $message, $code, $details));
    }

    /**
     * 404 Not Found response.
     *
     * @param string|null $resource Resource type (e.g., 'User')
     * @param string|int|null $identifier Resource identifier
     * @return ResponseInterface
     */
    public static function notFound(
        ?string $resource = null,
        string|int|null $identifier = null,
    ): ResponseInterface {
        if ($resource !== null && $identifier !== null) {
            $message = sprintf('%s with identifier %s not found', $resource, $identifier);
        } elseif ($resource !== null) {
            $message = sprintf('%s not found', $resource);
        } else {
            $message = 'Resource not found';
        }

        return self::json(404, self::errorBody($message, $message, 'NOT_FOUND'));
    }

    /**
     * 401 Unauthorized response.
     *
     * @param string|null $message Custom error message
     * @return ResponseInterface
     */
    public static function unauthorized(?string $message = null): ResponseInterface
    {
        $message = $message ?? 'Unauthorized';
        return self::json(401, self::errorBody($message, $message, 'UNAUTHORIZED'));
    }

    /**
     * 403 Forbidden response.
     *
     * @param string|null $message Custom error message
     * @return ResponseInterface
     */
    public static function forbidden(?string $message = null): ResponseInterface
    {
        $message = $message ?? 'Forbidden';
        return self::json(403, self::errorBody($message, $message, 'FORBIDDEN'));
    }

    /**
     * 422 Validation Error response.
     *
     * @param array<string, string|array<string>> $errors Field => error message(s)
     * @return ResponseInterface
     */
    public static function validationError(array $errors): ResponseInterface
    {
        return self::json(422, self::errorBody(
            'Validation failed',
            'The given data was invalid',
            'VALIDATION_ERROR',
            ['fields' => $errors],
        ));
    }

    /**
     * 405 Method Not Allowed response.
     *
     * @param string[] $allowedMethods Allowed HTTP methods
     * @return ResponseInterface
     */
    public static function methodNotAllowed(array $allowedMethods): ResponseInterface
    {
        $response = self::json(405, self::errorBody(
            'Method not allowed',
            sprintf('Allowed methods: %s', implode(', ', $allowedMethods)),
            'METHOD_NOT_ALLOWED',
        ));

        return $response->withHeader('Allow', implode(', ', $allowedMethods));
    }

    /**
     * 429 Too Many Requests response.
     *
     * @param int $retryAfter Seconds until retry is allowed
     * @return ResponseInterface
     */
    public static function tooManyRequests(int $retryAfter): ResponseInterface
    {
        $response = self::json(429, self::errorBody(
            'Too many requests',
            sprintf('Retry after %d seconds', $retryAfter),
            'TOO_MANY_REQUESTS',
        ));

        return $response->withHeader('Retry-After', (string) $retryAfter);
    }

    /**
     * 500 Internal Server Error response.
     *
     * @param string|null $message Custom error message
     * @param array<string, mixed>|null $debug Debug info (only include in dev!)
     * @return ResponseInterface
     */
    public static function serverError(
        ?string $message = null,
        ?array $debug = null,
    ): ResponseInterface {
        $userMessage = $message ?? 'Internal server error';
        $details = $debug !== null ? ['debug' => $debug] : null;

        return self::json(500, self::errorBody($userMessage, $userMessage, 'SERVER_ERROR', $details));
    }

    // ==================== Other Responses ====================

    /**
     * HTML response.
     *
     * @param string $content HTML content
     * @param int $status HTTP status code (default: 200)
     * @return ResponseInterface
     */
    public static function html(string $content, int $status = 200): ResponseInterface
    {
        return new Psr7Response(
            $status,
            ['Content-Type' => 'text/html; charset=utf-8'],
            $content,
        );
    }

    /**
     * Plain text response.
     *
     * @param string $content Text content
     * @param int $status HTTP status code (default: 200)
     * @return ResponseInterface
     */
    public static function text(string $content, int $status = 200): ResponseInterface
    {
        return new Psr7Response(
            $status,
            ['Content-Type' => 'text/plain; charset=utf-8'],
            $content,
        );
    }

    /**
     * Redirect response.
     *
     * @param string $url Target URL
     * @param int $status HTTP status code (default: 302)
     * @return ResponseInterface
     */
    public static function redirect(string $url, int $status = 302): ResponseInterface
    {
        return new Psr7Response($status, ['Location' => $url]);
    }

    /**
     * File download response.
     *
     * @param string $content File content
     * @param string $filename Download filename
     * @param string $contentType MIME type (default: 'application/octet-stream')
     * @return ResponseInterface
     */
    public static function download(
        string $content,
        string $filename,
        string $contentType = 'application/octet-stream',
    ): ResponseInterface {
        return new Psr7Response(
            200,
            [
                'Content-Type' => $contentType,
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
                'Content-Length' => (string) strlen($content),
            ],
            $content,
        );
    }

    // ==================== Internal Helpers ====================

    /**
     * Create a JSON response.
     *
     * @param int $status HTTP status code
     * @param array<string, mixed> $data Response data
     * @return ResponseInterface
     */
    private static function json(int $status, array $data): ResponseInterface
    {
        return new Psr7Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        );
    }

    /**
     * Build success response body.
     *
     * @param mixed $data Response data
     * @param string|null $message Success message
     * @param array<string, mixed>|null $meta Metadata
     * @return array<string, mixed>
     */
    private static function successBody(mixed $data, ?string $message, ?array $meta = null): array
    {
        $body = [
            'success' => true,
            'data' => $data,
        ];

        if ($message !== null) {
            $body['message'] = $message;
        }

        if ($meta !== null) {
            $body['meta'] = $meta;
        }

        return $body;
    }

    /**
     * Build error response body.
     *
     * @param string $userMessage User-facing error message
     * @param string $technicalMessage Technical error message
     * @param string|null $code Error code
     * @param array<string, mixed>|null $details Additional error details
     * @return array<string, mixed>
     */
    private static function errorBody(
        string $userMessage,
        string $technicalMessage,
        ?string $code = null,
        ?array $details = null,
    ): array {
        $error = [
            'message' => $technicalMessage,
        ];

        if ($code !== null) {
            $error['code'] = $code;
        }

        if ($details !== null) {
            $error['details'] = $details;
        }

        return [
            'success' => false,
            'message' => $userMessage,
            'error' => $error,
        ];
    }
}
