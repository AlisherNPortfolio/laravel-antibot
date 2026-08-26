# Redis

Redis is the package's only required infrastructure dependency (beyond
Laravel itself) for its core operation. It is used for:

- Sliding-window rate limiting (`RateAnalyzer`).
- Crawl-pattern tracking (`CrawlPatternAnalyzer`).
- Challenge failure counters (`ChallengeAnalyzer` / `ChallengeFailureTracker`).
- Challenge storage (`ChallengeStore`).
- Temporary block storage (`BlockStore`).
- The trusted-crawler verification cache (`TrustedBotManager`) — optional;
  degrades to per-request DNS lookups if unavailable rather than failing.

It is **not** used for the verification cookie (`VerificationService` is
fully stateless, self-contained, authenticated-encrypted) or for trusted-bot
DNS verification itself (only its cache).

## Configuration

```php
'redis' => [
    'connection' => env('ANTIBOT_REDIS_CONNECTION', 'default'), // key into config/database.php's 'redis' array
    'prefix' => 'antibot',
],
```

Any Laravel Redis connection works — phpredis or Predis, dedicated
connection or shared with the rest of your app. Every key the package
writes is namespaced under `{prefix}:`, e.g.:

```text
antibot:rate:{ip-hash}:10
antibot:block:{ip-hash}
antibot:violations:{ip-hash}
antibot:challenge:{id}
antibot:challenge-failures:{ip-hash}
antibot:crawl:{ip-hash}:paths
antibot:crawl:{ip-hash}:shapes
antibot:trusted-bot:{type}:{ip-hash}
```

Every key carries a TTL. None grow unbounded — set counters and sorted sets
are trimmed/expired on every write, and IPs (never arbitrary user input) are
always hashed before being used in a key.

## Failure strategy

```php
'failure_strategy' => env('ANTIBOT_FAILURE_STRATEGY', 'allow'),
```

- **`allow`** (default): if Redis is unreachable, `AntiBotManager` logs
  `antibot.redis_failure` and returns ALLOW. A Redis outage never takes your
  site down. This is the recommended default for most applications.
- **`block`**: the same failure returns BLOCK (403) for every request
  instead. Choose this only if you'd rather serve nothing than serve
  unprotected traffic during an outage — including to legitimate crawlers,
  since the temporary-block check (which needs Redis) runs before trusted-bot
  verification. See `docs/trusted-bots.md` for that specific interaction.

This is applied **once, centrally**, in `AntiBotManager::inspect()` — not
scattered across every Redis-touching class — by catching a single
package-level `AntiBotStoreException` that every Redis store/analyzer
raises on failure.

## Testing without a real Redis server

The package's own test suite never requires a running Redis instance: an
in-memory fake (`tests/Fakes/ArrayRedisConnection.php` +
`FakeRedisManager.php`) is bound in place of Laravel's Redis manager. If
you're writing tests for your own `Analyzer`/`TrustedBotVerifier` against
this package, the same fakes are a reasonable model to copy — the package
doesn't currently publish them as installable test helpers, since they're
tuned to exactly the Redis commands this package issues (see `docs/security.md`
for what atomicity guarantees they need to preserve).
