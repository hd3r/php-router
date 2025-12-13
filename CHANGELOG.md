# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **Security:** Cache signature key is now **required** when caching is enabled.
  - Prevents RCE via tampered cache files in shared hosting environments.
  - In debug mode (`debug=true`), caching is disabled so no key is needed.
  - Exception message provides clear guidance on how to fix.

### Fixed
- Invalid parameter types (e.g., `"abc"` for `:int`) now return `400 Bad Request` instead of `500 Internal Server Error`.
  - TypeError during parameter casting is a client error, not a server error.

## [1.1.1] - 2025-12-13

### Fixed
- `.gitattributes` now excludes PHPStan and CS-Fixer config from dist.

## [1.1.0] - 2025-12-13

### Added
- `ResponderInterface::getSuccessContentType()` for RFC 7807 compliance.
  - Success responses (2xx) use `application/json`.
  - Error responses (4xx/5xx) use responder's content type (e.g., `application/problem+json`).

### Fixed
- **Security:** `RedirectHandler` now only replaces `_route_params` with `rawurlencode()`.
  - Prevents injection of middleware-added attributes into redirect URLs.
  - Properly encodes special characters in redirect parameters.
- **Robustness:** Cache save with Closures now triggers error hook instead of throwing.
  - Routes with Closures work at runtime, caching is silently skipped.
  - Error hook receives `type: 'cache'` for logging.
- **DX:** `UrlGenerator` throws `RouterException` on unsupported `[]` syntax (hard throw, not via hook).
  - Prevents silent misbehavior when optional segments are used.
  - Clear error message: "Define separate routes instead."

### Changed
- `ResponderInterface` now requires `getSuccessContentType()` method.
  - **Breaking:** Custom responders must implement this method.

## [1.0.0] - 2025-12-07

### Added
- Initial release.
- **Routing** with support for GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD methods.
  - Dynamic route parameters with type constraints and auto-casting.
  - Route groups with prefix/middleware support.
  - Named routes for URL generation.
- **PSR-15 Middleware** support.
  - Global and route-specific middleware.
  - Middleware pipeline execution.
- **Route Caching** with HMAC validation for production environments.
- **Response Factory** with opinionated JSON response format.
  - Success/error response structure (`{success, data, error}`).
  - Common HTTP status helpers (ok, created, notFound, etc.).
  - File download and redirect support.
- **URL Generator** for named routes.
- **Event Hooks** for lifecycle events (before, after, error).
- **Exception Hierarchy**:
  - `RouterException` (base)
  - `NotFoundException`
  - `MethodNotAllowedException`
  - `RouteNotFoundException`
  - `DuplicateRouteException`
  - `CacheException`

[Unreleased]: https://github.com/hd3r/php-router/compare/v1.1.1...HEAD
[1.1.1]: https://github.com/hd3r/php-router/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/hd3r/php-router/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/hd3r/php-router/releases/tag/v1.0.0
