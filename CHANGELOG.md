# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2024-12-13

### Added
- Initial release.
- **Routing** with support for GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD methods.
  - Dynamic route parameters with regex constraints.
  - Optional parameters.
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

[Unreleased]: https://github.com/hd3r/php-router/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/hd3r/php-router/releases/tag/v1.0.0
