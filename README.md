# Laravel AntiBot

Application-agnostic anti-bot protection for Laravel: risk-based scoring,
sliding-window rate limiting, a browser proof-of-work challenge, temporary
IP blocking, and **network-verified** search-engine crawler bypass
(Googlebot, Bingbot) that never trusts a User-Agent header by itself.

Works identically for a blog, a marketplace, a public API, a documentation
site, or anything else — the package contains no application-specific logic
and knows nothing about your routes beyond what you tell it via
configuration.

## Why not just check the User-Agent for "Googlebot"?

Because it's trivially spoofable. This package verifies a claimed crawler
via reverse+forward DNS confirmation against the crawler's official domain
before ever granting it a bypass. See [docs/trusted-bots.md](docs/trusted-bots.md).

## Installation

```bash
composer require alishernportfolio/laravel-antibot
```

Laravel's package auto-discovery registers the service provider and the
`antibot` middleware alias automatically — no manual edits to
`bootstrap/providers.php`/`config/app.php` are needed.

Publish the config (recommended):

```bash
php artisan vendor:publish --tag=antibot-config
```

Optional — only if you want the (disabled-by-default) database event log:

```bash
php artisan vendor:publish --tag=antibot-migrations
php artisan migrate
```

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- Redis (via `illuminate/redis`, phpredis or Predis) — required for the
  core protection pipeline; see [docs/redis.md](docs/redis.md)

## Usage

```php
use Illuminate\Support\Facades\Route;

Route::middleware('antibot')->group(function () {
    Route::get('/crossword/{id}', ...);
    Route::get('/blog/{slug}', ...);
    Route::post('/api/comments', ...);
});
```

That's it. Normal traffic passes through untouched; suspicious traffic sees
a lightweight interstitial puzzle; abusive traffic is temporarily blocked;
verified Googlebot/Bingbot traffic always passes straight through.

### Global disable

```env
ANTIBOT_ENABLED=false
```

## How it decides

```text
Request → Context → Temporary Block Check → Trusted Bot Verification
                                                        │
                                          not verified  │  verified
                                                ▼        ▼
                                        Risk Score Engine   ALLOW
                                          │    │     │
                                       ALLOW CHALLENGE BLOCK
```

Full explanation, including exactly why trusted-bot verification runs
*before* risk scoring: [docs/architecture.md](docs/architecture.md).

## Configuration

Every threshold, score, window, and duration is configurable —
[docs/configuration.md](docs/configuration.md) documents every key in
`config/antibot.php`.

## Trusted search engine crawlers

[docs/trusted-bots.md](docs/trusted-bots.md) — the DNS verification
procedure, its documented limitations, caching, failure behavior, and how
to register your own trusted-crawler verifier.

## Extensibility

- Custom risk analyzers — [docs/custom-analyzers.md](docs/custom-analyzers.md)
- Custom trusted-bot verifiers — [docs/trusted-bots.md](docs/trusted-bots.md)
- Custom challenge providers (Turnstile, hCaptcha, reCAPTCHA, ...) and
  custom CHALLENGE/BLOCK responses — [docs/custom-challenges.md](docs/custom-challenges.md)

## Route exclusions

```php
// config/antibot.php
'exclude' => [
    'routes' => ['health', 'webhooks.*'],
    'paths' => ['webhooks/*'],
],
```

## API vs. browser responses

Browser requests to a CHALLENGE decision see an interstitial HTML page with
the proof-of-work puzzle. API/JSON clients instead receive:

```json
{
    "message": "Verification required.",
    "challenge_url": "https://your-app.test/anti-bot/challenge"
}
```

Neither response ever exposes the internal risk score, analyzer names, or
trusted-bot verification details.

## Redis

Required for rate limiting, challenge storage, temporary blocking, and (as
an optional performance cache only) trusted-bot verification. A Redis
outage fails open (`ALLOW`, logged) by default, or closed (`BLOCK`) if you
configure `ANTIBOT_FAILURE_STRATEGY=block`. See [docs/redis.md](docs/redis.md).

## Nginx

Optional, complementary infrastructure-level rate limiting —
[docs/nginx.md](docs/nginx.md). This package does not require Nginx.

## robots.txt

This package does not read, write, or otherwise interact with `robots.txt`
or your sitemap. It is an abuse-protection layer, not a crawler-policy
mechanism — the two work independently and simultaneously.

## Privacy

No invasive fingerprinting (no canvas/WebGL/audio/font fingerprinting).
IPs, session IDs, and user agents are always hashed (`HMAC-SHA256`, keyed
with `APP_KEY`) before being used in a cache key or a log line. Full details:
[docs/privacy.md](docs/privacy.md).

## Security limitations

Documented candidly in [docs/security.md](docs/security.md), including what
this package does and does not protect against, and current known
limitations (DNS-only crawler verification in v1, best-effort DNS timeouts,
proof-of-work as a friction mechanism rather than a hard guarantee).

## Testing

```bash
composer install
composer test        # Pest, via Orchestra Testbench — no real Redis server required
composer analyse      # PHPStan (level 8)
```

The test suite binds an in-memory fake in place of Laravel's Redis manager
(`tests/Fakes/ArrayRedisConnection.php`), so it runs entirely offline and
without any external service.

## Contributing

Issues and pull requests are welcome. Please include tests for any
behavioral change — see `tests/Unit` and `tests/Feature` for the existing
patterns (in particular, DNS-dependent code must go through the
`Contracts\DnsResolver` abstraction and be tested with `FakeDnsResolver`,
never real network calls).

## License

MIT. See [LICENSE](LICENSE).
