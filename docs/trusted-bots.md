# Trusted Search Engine Crawlers

## Why the User-Agent header cannot be trusted

Any HTTP client can send `User-Agent: Googlebot/2.1`. It proves nothing about
who is actually making the request — it is exactly as trustworthy as a
self-reported name. Treating that header as proof of identity would let any
scraper bypass every protection in this package simply by copying a string.

For that reason, `laravel-antibot` never grants trust based on the
User-Agent alone. A claimed crawler is only trusted after its network
identity has been independently confirmed.

## How verification works

For a request whose User-Agent claims to be Googlebot, Bingbot or YandexBot,
`TrustedBotManager` runs the matching `TrustedBotVerifier`, which performs:

1. **Reverse DNS lookup** of the request's source IP (PTR record) — Google:
   [official guidance](https://developers.google.com/search/docs/crawling-indexing/verifying-googlebot);
   Bing:
   [official guidance](https://blogs.bing.com/webmaster/August-2012/How-to-Verify-that-Bingbot-is-Bingbot/);
   Yandex:
   [official guidance](https://yandex.com/support/webmaster/robot-workings/check-robot.html).
2. **Domain ownership check** — the resulting hostname must end with one of
   the crawler's official domains, on a strict subdomain boundary (so
   `evilgooglebot.com` is correctly rejected even though it contains the
   substring `googlebot.com`):
   - Googlebot: `googlebot.com`, `google.com`, `googleusercontent.com`
   - Bingbot: `search.msn.com`
   - YandexBot: `yandex.ru`, `yandex.net`, `yandex.com`
3. **Forward DNS lookup** of that hostname.
4. **IP confirmation** — the forward lookup must include the original
   request IP.

Only if all four steps succeed is the request treated as a verified crawler.
Anything else — no PTR record, a hostname outside the official domains, or a
forward lookup that doesn't resolve back to the original IP — is **not
trusted** and falls through to ordinary risk analysis like any other client.

```text
User-Agent claims Googlebot
        ↓
Reverse DNS (PTR) on the source IP
        ↓
Hostname ends with googlebot.com / google.com / googleusercontent.com?
        ↓ yes
Forward DNS (A/AAAA) on that hostname
        ↓
Original IP present in the result?
        ↓ yes
Verified Googlebot
```

## Limitation: DNS-based verification only (v1)

Google and Bing also publish IP-range files as an alternative to DNS lookups
(Google's `*.json` files under `/static/crawling/ipranges/`, Bing's
`bingbot.json`). Yandex does not publish an equivalent list and documents DNS
verification as the method. v1 of this package implements DNS verification
only — IP range matching is a reasonable future addition but is out of scope
for now. Consult each vendor's current documentation before relying on this
package's verification for a security-critical decision; crawler
verification mechanisms can change without notice.

## DNS abstraction and testing

All DNS lookups go through the `DnsResolver` contract:

```php
interface DnsResolver
{
    public function reverse(string $ip): ?string;
    public function resolve(string $hostname): array;
}
```

Production binds `SystemDnsResolver` (PHP's native resolver functions).
Tests use `FakeDnsResolver`, so verification logic is deterministic and
never depends on real, external DNS — see
`tests/Unit/TrustedBots/GoogleBotVerifierTest.php`,
`BingBotVerifierTest.php` and `YandexBotVerifierTest.php`.

**Timeout limitation:** PHP has no reliable, cross-platform, per-call DNS
timeout API. `SystemDnsResolver` sets `default_socket_timeout` around each
lookup as a best-effort guard (`antibot.trusted_bots.verification.dns_timeout_seconds`),
but depending on the platform and PHP build this may not be strictly
enforced. For a hard guarantee, configure a short resolver timeout at the OS
level, e.g. `options timeout:1 attempts:1` in `/etc/resolv.conf` on Linux.

## Caching

A successful verification is cached for `antibot.trusted_bots.cache.ttl_seconds`
(default 3600s) under `antibot:trusted-bot:{type}:{ip-hash}`, so DNS lookups
only happen occasionally per IP rather than on every request. The IP is
hashed (HMAC, keyed with `APP_KEY`) before it is used in the key.

A **failed** claim is also cached, but only briefly
(`antibot.trusted_bots.cache.negative_ttl_seconds`, default 30s). This is a
deliberate addition beyond a minimal implementation: without it, an attacker
could send repeated requests with a spoofed `Googlebot` User-Agent from a
single IP to force a fresh DNS lookup on every single request — a small
DNS-based resource-exhaustion vector. The short TTL still lets a genuinely
new crawler IP (or a transient DNS blip) verify again quickly.

Only the completed verification result (type, verified flag, hostname) is
cached — never the raw claim ("UA said Googlebot") — and it is not exposed
in any HTTP response.

If Redis is unavailable when reading or writing this cache, verification
**degrades to a fresh DNS check on every request** rather than failing. DNS
verification does not depend on Redis for anything except this optional
cache, so a Redis outage never prevents a legitimate crawler from being
verified — see `TrustedBotManagerTest::degrades to a fresh DNS check`.

## Interaction with the temporary-block check

The package's decision flow checks for an existing temporary block *before*
trusted-bot verification (see `docs/architecture.md`). This means a client
that was previously blocked (e.g. flooding before ever claiming to be a
crawler) will still be blocked even if it later claims — and could
genuinely verify as — Googlebot. This is intentional: a temporary block is a
short-lived punitive state, not a permanent verdict, and it protects against
an attacker discovering that switching their User-Agent to `Googlebot`
clears an existing block.

One consequence worth knowing: if Redis is unavailable and
`antibot.failure_strategy` is set to `block`, **every** request — including
requests from real, verifiable crawlers — is blocked, because the block
check itself cannot run without Redis. This is a deliberate "fail closed for
everyone during an infrastructure outage" trade-off; if you'd rather crawlers
keep flowing during a Redis outage, use the default `allow` strategy instead.

## Bypassing AntiBot vs. bypassing everything

A verified crawler bypasses this package's own challenge/risk-scoring/block
logic (`antibot.trusted_bots.bypass_challenge` / `bypass_block`, both `true`
by default). It does **not** automatically bypass infrastructure-level
protections you run independently, such as Nginx rate limiting (see
`docs/nginx.md`) or a CDN/WAF. Do not treat "verified by this package" as a
justification for unlimited traffic at the infrastructure layer.

## Registering additional trusted crawlers

```php
use AlisherNPortfolio\LaravelAntiBot\Contracts\TrustedBotVerifier;
use AlisherNPortfolio\LaravelAntiBot\Contracts\DnsResolver;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\DTO\TrustedBotResult;
use AlisherNPortfolio\LaravelAntiBot\Enums\TrustedBotType;

class MyTrustedBotVerifier implements TrustedBotVerifier
{
    public function __construct(private readonly DnsResolver $dns) {}

    public function type(): TrustedBotType
    {
        return TrustedBotType::GOOGLEBOT; // or add a new case to your own enum/type scheme
    }

    public function supports(AntiBotContext $context): bool
    {
        return str_contains(strtolower($context->userAgent), 'my-crawler');
    }

    public function verify(AntiBotContext $context): TrustedBotResult
    {
        // Your own network-verified check. Never trust the User-Agent alone.
        // ...
    }
}
```

```php
// config/antibot.php
'trusted_bots' => [
    'providers' => [
        \AlisherNPortfolio\LaravelAntiBot\TrustedBots\GoogleBotVerifier::class,
        \AlisherNPortfolio\LaravelAntiBot\TrustedBots\BingBotVerifier::class,
        \AlisherNPortfolio\LaravelAntiBot\TrustedBots\YandexBotVerifier::class,
        \App\AntiBot\MyTrustedBotVerifier::class,
    ],
],
```

`TrustedBotType` currently has `GOOGLEBOT`, `BINGBOT` and `YANDEXBOT` cases.
If you need a distinct type label for your own verifier (rather than reusing
an existing one for metadata/logging purposes), extend the enum in a fork or
open an issue — keeping the core engine free of hard-coded search engines is
a deliberate design choice (see `docs/architecture.md`).

## Never a general whitelist

There is no mechanism, and none should be added, where a User-Agent alone
grants trust. Every `TrustedBotVerifier` must perform genuine network-level
verification. Generic crawlers (AhrefsBot, SemrushBot, MJ12bot, Scrapy, curl,
etc.) are never automatically trusted — a host application that wants to
treat them specially must do so explicitly, e.g. via a custom `Analyzer`.

## Social link-preview bots (Telegram, Facebook, ...)

When a link to a protected page is shared in Telegram, Slack, Discord,
WhatsApp, or a similar app, that app's server fetches the page once to build
a preview card (title/image) from its `<meta>` tags. These fetchers:

- cannot run JavaScript, so they cannot solve the proof-of-work challenge;
- are **not** trusted crawlers in this package's sense — unlike Google, Bing
  and Yandex, they don't publish a documented reverse+forward DNS (or
  equivalent) verification procedure this package could check, so no
  `TrustedBotVerifier` exists for them and none should be added under the
  User-Agent alone (see "Never a general whitelist" above).

Practically, this means such a fetcher is scored like any other anonymous
client. A single preview fetch normally scores 0 and is `ALLOW`ed. It can
still be `CHALLENGE`d or `BLOCK`ed under load, though — most notably because
`RateAnalyzer` and `CrawlPatternAnalyzer` key purely by source IP across the
*entire* site, and these platforms fetch previews for *all* their users
through a small, shared pool of server IPs. A popular page shared many times
in a short window can make that shared IP pool look like a single client
issuing a burst of requests. When that happens, the resulting `CHALLENGE`/
`BLOCK` response replaces the real page (and its preview `<meta>` tags), so
the link preview breaks for everyone on that platform.

### Diagnosing it

Set `ANTIBOT_LOG_LINK_PREVIEW_BOTS=true` (`antibot.link_preview_bots.log_when_affected`,
default `false`) to log a distinct `antibot.link_preview_bot_affected` event
(via `LinkPreviewBotDetector`, matched against
`antibot.link_preview_bots.patterns`) whenever a request whose User-Agent
matches a known link-preview fetcher is `CHALLENGE`d or `BLOCK`ed. **This
performs no bypass and never changes the decision** — it exists purely so
you can confirm the problem is actually happening before changing anything.

### Fixing it

If the log confirms real-world impact, add the specific affected route(s) —
typically public, read-only content pages — to `exclude.routes` /
`exclude.paths` (see "Exclusions" in the main README). This is a deliberate,
scoped trade-off the host application makes for its own routes; the package
itself will never make it for you, since excluding a route also removes its
scraping/flooding protection.

Do not "fix" this by giving these User-Agents a lower risk score or a
bypass — see "Never a general whitelist" above.
