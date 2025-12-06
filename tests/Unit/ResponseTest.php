<?php

declare(strict_types=1);

namespace Hd3r\Router\Tests\Unit;

use Hd3r\Router\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testSuccessResponse(): void
    {
        $response = Response::success(['id' => 1, 'name' => 'Test']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $body = json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertSame(['id' => 1, 'name' => 'Test'], $body['data']);
    }

    public function testSuccessWithMessage(): void
    {
        $response = Response::success(['id' => 1], 'Created successfully');

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('Created successfully', $body['message']);
    }

    public function testCreatedResponse(): void
    {
        $response = Response::created(['id' => 5], 'User created', '/users/5');

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('/users/5', $response->getHeaderLine('Location'));
    }

    public function testNoContentResponse(): void
    {
        $response = Response::noContent();

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testNotFoundResponse(): void
    {
        $response = Response::notFound('User', 123);

        $this->assertSame(404, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('User', $body['message']);
        $this->assertStringContainsString('123', $body['message']);
    }

    public function testMethodNotAllowedResponse(): void
    {
        $response = Response::methodNotAllowed(['GET', 'POST']);

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET, POST', $response->getHeaderLine('Allow'));
    }

    public function testValidationErrorResponse(): void
    {
        $response = Response::validationError([
            'email' => 'Invalid email format',
            'password' => 'Too short',
        ]);

        $this->assertSame(422, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('VALIDATION_ERROR', $body['error']['code']);
        $this->assertArrayHasKey('fields', $body['error']['details']);
    }

    public function testServerErrorResponse(): void
    {
        $response = Response::serverError('Something went wrong', ['trace' => '...']);

        $this->assertSame(500, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('SERVER_ERROR', $body['error']['code']);
    }

    public function testRedirectResponse(): void
    {
        $response = Response::redirect('/new-location', 301);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/new-location', $response->getHeaderLine('Location'));
    }

    public function testHtmlResponse(): void
    {
        $response = Response::html('<h1>Hello</h1>');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('<h1>Hello</h1>', (string) $response->getBody());
    }

    public function testSuccessWithMeta(): void
    {
        $response = Response::success(['items' => [1, 2, 3]], null, ['count' => 3]);

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(3, $body['meta']['count']);
    }

    public function testAcceptedResponse(): void
    {
        $response = Response::accepted(['job_id' => 'abc123'], 'Processing started');

        $this->assertSame(202, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('abc123', $body['data']['job_id']);
        $this->assertSame('Processing started', $body['message']);
    }

    public function testPaginatedResponse(): void
    {
        $response = Response::paginated(
            items: [['id' => 1], ['id' => 2]],
            total: 50,
            page: 2,
            perPage: 10
        );

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertCount(2, $body['data']);
        $this->assertSame(50, $body['meta']['pagination']['total']);
        $this->assertSame(2, $body['meta']['pagination']['current_page']);
        $this->assertSame(5, $body['meta']['pagination']['last_page']);
        $this->assertSame(11, $body['meta']['pagination']['from']);
        $this->assertSame(20, $body['meta']['pagination']['to']);
    }

    public function testErrorResponse(): void
    {
        $response = Response::error('Something failed', 400, 'CUSTOM_ERROR', ['field' => 'value']);

        $this->assertSame(400, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('CUSTOM_ERROR', $body['error']['code']);
        $this->assertSame('value', $body['error']['details']['field']);
    }

    public function testUnauthorizedResponse(): void
    {
        $response = Response::unauthorized();
        $this->assertSame(401, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('UNAUTHORIZED', $body['error']['code']);

        // With custom message
        $response2 = Response::unauthorized('Token expired');
        $body2 = json_decode((string) $response2->getBody(), true);
        $this->assertSame('Token expired', $body2['message']);
    }

    public function testForbiddenResponse(): void
    {
        $response = Response::forbidden();
        $this->assertSame(403, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('FORBIDDEN', $body['error']['code']);

        // With custom message
        $response2 = Response::forbidden('Access denied to resource');
        $body2 = json_decode((string) $response2->getBody(), true);
        $this->assertSame('Access denied to resource', $body2['message']);
    }

    public function testTooManyRequestsResponse(): void
    {
        $response = Response::tooManyRequests(60);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('60', $response->getHeaderLine('Retry-After'));

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('TOO_MANY_REQUESTS', $body['error']['code']);
    }

    public function testTextResponse(): void
    {
        $response = Response::text('Plain text content', 201);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('Plain text content', (string) $response->getBody());
    }

    public function testDownloadResponse(): void
    {
        $response = Response::download('file content', 'report.csv', 'text/csv');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/csv', $response->getHeaderLine('Content-Type'));
        $this->assertSame('attachment; filename="report.csv"', $response->getHeaderLine('Content-Disposition'));
        $this->assertSame('12', $response->getHeaderLine('Content-Length'));
        $this->assertSame('file content', (string) $response->getBody());
    }

    public function testNotFoundVariants(): void
    {
        // Only resource name
        $response1 = Response::notFound('User');
        $body1 = json_decode((string) $response1->getBody(), true);
        $this->assertSame('User not found', $body1['message']);

        // No parameters
        $response2 = Response::notFound();
        $body2 = json_decode((string) $response2->getBody(), true);
        $this->assertSame('Resource not found', $body2['message']);
    }

    public function testCreatedWithoutLocation(): void
    {
        $response = Response::created(['id' => 1]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('', $response->getHeaderLine('Location'));
    }

    public function testServerErrorDefaults(): void
    {
        $response = Response::serverError();

        $this->assertSame(500, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('Internal server error', $body['message']);
        $this->assertArrayNotHasKey('details', $body['error']);
    }

    public function testHtmlWithStatus(): void
    {
        $response = Response::html('<h1>Error</h1>', 500);

        $this->assertSame(500, $response->getStatusCode());
    }
}
