<?php

declare(strict_types=1);

namespace Hd3r\Router\Contract;

/**
 * Interface for response body formatting.
 *
 * Implementations define the JSON structure for API responses.
 * Default: JsonResponder with {success, data, error} format.
 * Future: RfcResponder for RFC 7807 Problem Details.
 */
interface ResponderInterface
{
    /**
     * Format a success response body.
     *
     * @param mixed $data Response data
     * @param string|null $message Optional success message
     * @param array<string, mixed>|null $meta Optional metadata (pagination, etc.)
     *
     * @return array<string, mixed> Formatted response body
     */
    public function formatSuccess(mixed $data, ?string $message = null, ?array $meta = null): array;

    /**
     * Format an error response body.
     *
     * @param string $message User-facing error message
     * @param string|null $code Error code (e.g., 'NOT_FOUND', 'VALIDATION_ERROR')
     * @param array<string, mixed>|null $details Additional error details
     *
     * @return array<string, mixed> Formatted response body
     */
    public function formatError(string $message, ?string $code = null, ?array $details = null): array;

    /**
     * Get the Content-Type header value.
     *
     * @return string MIME type (e.g., 'application/json', 'application/problem+json')
     */
    public function getContentType(): string;
}
