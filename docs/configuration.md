# Configuration

Publish the config file to customize anything below:

```bash
php artisan vendor:publish --tag=antibot-config
```

This writes `config/antibot.php`. Every key mirrors a constructor argument
somewhere in the package — see `src/AntiBotServiceProvider.php` if you want
to trace exactly how a given key is used.

## Master switch

```php
'enabled' => env('ANTIBOT_ENABLED', true),
```

When `false`, `AntiBotMiddleware` calls `next($request)` immediately. No
Redis operation, DNS lookup, or analyzer runs.

## Decision thresholds

```php
'decision' => [
    'allow_max_score' => 30,     // score <= this -> ALLOW
    'challenge_max_score' => 70, // this < score <= 70 -> CHALLENGE; above -> BLOCK
],
```

## Rate limits

Each window carries its own limit *and* score contribution:

```php
'rate_limits' => [
    '10_seconds' => ['seconds' => 10, 'limit' => 20, 'score' => 40],
    '1_minute'   => ['seconds' => 60, 'limit' => 100, 'score' => 30],
    '5_minutes'  => ['seconds' => 300, 'limit' => 300, 'score' => 20],
],
```

Add, remove, or rename windows freely — `RateAnalyzer` iterates whatever is
configured.

## Scoring

```php
'scoring' => [
    'suspicious_user_agent' => 20,
    'missing_user_agent' => 15,
    'spoofed_trusted_bot_claim' => 40, // claims a trusted crawler but failed DNS verification
    'rapid_crawling' => 30,
    'challenge_failure_1' => 20,
    'challenge_failure_2' => 30,
    'challenge_failure_3_plus' => 50,
    'successful_verification' => -30, // negative: reduces risk
],
```

## User-Agent patterns

```php
'user_agent' => [
    'suspicious_patterns' => ['curl', 'wget', 'python-requests', ...],

    // Claimed crawler names scored as `spoofed_trusted_bot_claim` (above)
    // instead of an ordinary unrecognized User-Agent. Keep this in sync
    // with `trusted_bots.providers` — see "Registering additional trusted
    // crawlers" in docs/trusted-bots.md.
    'trusted_bot_claim_patterns' => ['googlebot', 'bingbot', 'yandexbot'],
],
```

## Crawl pattern detection

```php
'crawling' => [
    'enabled' => true,
    'window_seconds' => 60,
    'max_paths' => 100,               // distinct paths in the window before flagging breadth
    'enumeration_min_requests' => 10, // minimum volume before enumeration is even considered
    'enumeration_ratio_threshold' => 5, // raw-paths / distinct-shapes ratio that flags enumeration
],
```

## Challenge

```php
'challenge' => [
    'enabled' => true,
    'challenge_ttl_seconds' => 60,
    'default_difficulty' => 16,       // leading zero *bits* required
    'adaptive_difficulty' => true,    // scale up (capped +8 bits) with the request's risk score
    'max_attempts' => 3,
    'failure_tracking_ttl_seconds' => 900,
    'route_rate_limit' => '60,1',
    'verify_rate_limit' => '20,1',
],
```

## Verification cookie

```php
'verification' => [
    'cookie_name' => 'antibot_verified',
    'ttl_minutes' => 30,
    'secure' => true,   // set false only for local http:// development
    'same_site' => 'lax',
],
```

## Blocking

```php
'blocking' => [
    'enabled' => true,
    'durations' => ['first' => 5, 'second' => 30, 'third' => 60, 'repeat' => 360], // minutes
    'violation_ttl_seconds' => 86400, // escalation resets after this long with no new violation
],
```

## Trusted bots

See `docs/trusted-bots.md` for the full explanation. Key config:

```php
'trusted_bots' => [
    'enabled' => true,
    'bypass_challenge' => true,
    'bypass_block' => true,
    'providers' => [GoogleBotVerifier::class, BingBotVerifier::class],
    'cache' => ['enabled' => true, 'ttl_seconds' => 3600, 'negative_ttl_seconds' => 30],
    'verification' => ['dns_timeout_seconds' => 2],
],
```

## Exclusions

```php
'exclude' => [
    'routes' => ['health', 'webhooks.*'], // route *names*, wildcards supported
    'paths' => ['webhooks/*'],            // URL paths, wildcards supported
],
```

An excluded request skips `AntiBotManager` entirely — no Redis operation, no
DNS lookup, no scoring.

## Social link-preview bots

See `docs/trusted-bots.md` ("Social link-preview bots") for the full
explanation. Diagnostic only — never changes a decision.

```php
'link_preview_bots' => [
    'log_when_affected' => env('ANTIBOT_LOG_LINK_PREVIEW_BOTS', false),
    'patterns' => ['telegrambot', 'facebookexternalhit', 'twitterbot', ...],
],
```

## Logging

```php
'logging' => [
    'enabled' => true,
    'store_database_events' => env('ANTIBOT_STORE_DATABASE_EVENTS', false), // optional; requires the published migration
    // Rows older than this are deleted by the `antibot:prune-events` command
    // (auto-scheduled daily). 0 disables pruning — see docs/privacy.md.
    'retention_days' => env('ANTIBOT_LOG_RETENTION_DAYS', 30),
],
```

See `docs/security.md` for exactly what is (and is never) logged.

## Redis

```php
'redis' => [
    'connection' => env('ANTIBOT_REDIS_CONNECTION', 'default'), // any connection in config/database.php's 'redis' array
    'prefix' => 'antibot',
],
```

## Failure strategy

```php
'failure_strategy' => env('ANTIBOT_FAILURE_STRATEGY', 'allow'), // 'allow' or 'block'
```

See `docs/redis.md`.

## Routes

```php
'routes' => [
    'enabled' => true,
    'prefix' => 'anti-bot',
    'middleware' => ['web'],
],
```

Set `enabled` to `false` to disable the bundled `GET /{prefix}/challenge` and
`POST /{prefix}/verify` routes entirely and implement your own — the
`Contracts\ChallengeProvider` / `Contracts\ChallengeStore` /
`Services\VerificationService` pieces remain usable independently of the
bundled HTTP layer.
