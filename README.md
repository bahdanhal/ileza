# IleZa.pl (`ileza`)

**Live website:** [IleZa.pl - editorial fair prices and Polish salary calculator](https://ileza.pl/)

Manual editorial fair-price histories for products in Poland, paired with a private browser-based salary and income calculator.

**Related projects:** [Bahdan Hal - software engineering consulting](https://bahdanhal.pl/) · [Stackhal - free developer and DevOps tools](https://stackhal.com/)

**Shared Packagist packages:** [`bahdan/symfony-safe-http-client`](https://packagist.org/packages/bahdan/symfony-safe-http-client) · [`bahdan/symfony-privacy-analytics-bundle`](https://packagist.org/packages/bahdan/symfony-privacy-analytics-bundle) · [`bahdan/lead-capture-bundle`](https://packagist.org/packages/bahdan/lead-capture-bundle)

---

## 1. Overview

**IleZa.pl** ("Ile za?") publishes transparent editorial judgments about what products are reasonably worth without premiums for novelty, packaging, marketing, or large retail markups. It is designed to cover any product category, including electronics, vehicles, and cosmetics, alongside a browser-only Polish employment and tax comparison calculator.

### Core Features & Canonical Routes:
- **Fair-Price Catalog (`/`, PL: `/pl/`)**: Catalog of products with manual editorial price estimates.
- **Product History (`/ceny/{slug}`, EN: `/prices/{slug}`)**: Dated editorial fair prices, reasonable ranges, confidence, and historical changes.
- **Employment & Tax Calculator (`/kalkulator-wynagrodzen`, EN: `/salary-calculator`)**: Browser-only comparison of UoP, Umowa Zlecenie, Umowa o Dzieło, and B2B from a single company employer budget using 2026 Polish tax rules.
- **Product Addition Request (`/zglos`, EN: `/request`)**: Community submission queue for new models.
- **Price Tip Submission (`/ceny/{slug}/okazja`, EN: `/prices/{slug}/price-tip`)**: Community price alerts for admin review.
- **Model Context Protocol (`POST /mcp`)**: MCP tools for price lookups, tax calculations, and authenticated admin operations.

---

## 2. Verification

```bash
docker build --target test -t bahdan-landing-test .
docker run --rm --env-file .env.example -e APP_ENV=test bahdan-landing-test vendor/bin/phpunit --fail-on-phpunit-notice
docker run --rm bahdan-landing-test vendor/bin/phpstan analyse --no-progress --memory-limit=512M
docker run --rm bahdan-landing-test vendor/bin/phpcs
```

---

## 3. Privacy & Security Invariants

- **Zero Marketplace Scraping**: The service never crawls marketplace pages automatically.
- **Voluntary Community Price Tips**: Submitted URLs are stripped of tracking parameters, never fetched automatically, stored privately for manual review, and automatically pruned after 90 days.
- **IP Hashing**: Client IP addresses are irreversibly hashed using HMAC-SHA256 before persistence.
- **Database Isolation**: PostgreSQL operates exclusively within an internal Docker network.
