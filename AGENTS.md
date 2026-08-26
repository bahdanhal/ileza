# AI Agent Guidelines & System Instructions — ileza (ileza.pl)

This document provides mandatory directives for all AI coding agents interacting with the `ileza` repository (`ileza.pl`).

---

## 1. Language Constraints (CRITICAL)

- **Strict English Requirement**: All code generated, edited, or reviewed MUST be in **English only**.
  - All identifiers (classes, methods, functions, variables, constants, properties, arguments).
  - All inline comments, block comments, docblocks (`/** ... */`), and PHPDoc tags.
  - All commit messages, PR descriptions, and markdown documentation.
- **Allowed Exceptions**:
  - Domain-specific legal/tax terms in Polish contract/income calculations where no standard English term exists (`UoP`, `umowa zlecenie`, `umowa o dzieło`, `B2B`, `grosz`, `ryczałt`, `ZUS`).
  - Translation string files in `translations/` (e.g. `messages.pl.yaml`).
  - UI copy specifically intended for Polish routes (`/`, `/kalkulator-wynagrodzen`, etc.).

---

## 2. Testing & Quality Invariants (100% Green CI Required)

Before completing any task, ensure the verification matrix passes cleanly in `ileza`:

```bash
docker run --rm -v "$PWD:/app" -w /app/ileza -e APP_ENV=test bahdan-landing-test vendor/bin/phpunit --fail-on-phpunit-notice
docker run --rm -v "$PWD:/app" -w /app/ileza -e APP_ENV=test node:24-alpine sh -lc 'for test in tests/js/*.test.js; do node "$test" || exit 1; done'
docker run --rm -v "$PWD:/app" -w /app/ileza bahdan-landing-test vendor/bin/phpstan analyse --no-progress --memory-limit=512M
docker run --rm -v "$PWD:/app" -w /app/ileza bahdan-landing-test vendor/bin/phpcs
docker run --rm -v "$PWD:/app" -w /app/ileza bahdan-landing-test php bin/console lint:twig templates
docker run --rm -v "$PWD:/app" -w /app/ileza bahdan-landing-test php bin/console lint:yaml translations config
docker run --rm -v "$PWD:/app" -w /app/ileza bahdan-landing-test composer validate --strict --no-check-publish
```

---

## 3. Application-Specific Invariants

- **Ileza market pages**: Repeated price access must preload observation histories in one repository call. Home and hub code must call `GetProductPriceHistory::preload()` before iterating products. Preserve request-local caching in `ProductCatalog`; tests should reject per-product `history()` or `latest()` calls on these paths.
- **Schema.org Structured Data**: Editorial price history pages must declare `@type: Dataset`, `BreadcrumbList`, and `WebPage`. Never use `Product` or `AggregateOffer` markup on market pages.
- **Marketplace tip privacy**: Voluntarily submitted listing URLs must have query strings stripped, expire after 90 days, and remain strictly in private admin storage. Client IPs must be hashed with HMAC-SHA256.

