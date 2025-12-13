# Development

## Requirements

- PHP 8.1+
- Composer

## Setup

```bash
git clone <repository-url>
cd php-router
composer install
```

## Running Tests

```bash
# Unit tests only
composer test

# All test suites
composer test:all

# With coverage
composer test:coverage
```

## Code Quality

```bash
# PHPStan static analysis
composer analyse

# PHP CS Fixer (check)
composer cs

# PHP CS Fixer (fix)
composer cs:fix

# Full CI pipeline
composer ci
```
