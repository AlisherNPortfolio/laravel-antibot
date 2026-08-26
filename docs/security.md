# Security

## Threats this package addresses, and how

| Threat | Mitigation |
|---|---|
| Challenge replay | `ChallengeStore::consume()` is an atomic fetch-and-delete (a Redis `MULTI`/`EXEC` transaction), so a correct answer can only ever be accepted once, even under concurrent requests for the same challenge ID. See `ChallengeServiceTest::verifies a correctly solved challenge exactly once`. |
| Challenge tampering | The client only ever submits a `counter`. Difficulty, the nonce, and expiry are all server-held and re-derived from `ChallengeStore` — never trusted from client input. |
| Expired challenge | `Challenge::isExpired()` is checked in `ChallengeService::verify()` in addition to the Redis key's own TTL. |
| Challenge brute force / endpoint abuse | Every failed `verify()` call (wrong answer, unknown/expired challenge, or replay) increments a per-client failure counter (`ChallengeFailureTracker`) that both raises future risk scores and, after `max_attempts`, burns the challenge outright. The `POST /verify` and `GET /challenge` routes also carry their own `throttle` middleware (`antibot.challenge.verify_rate_limit` / `route_rate_limit`), independent of the main risk engine. |
| Verification cookie forgery | The cookie value is authenticated-encrypted via Laravel's `Crypt` facade (AES-256-CBC with an HMAC, keyed by `APP_KEY`) — not a plain `verified=true` flag. Tampering invalidates decryption. |
| Fake search engine crawlers / User-Agent spoofing | `TrustedBotManager` never trusts the User-Agent; see `docs/trusted-bots.md` for the full reverse+forward DNS procedure, including the "lookalike domain" boundary check (`evilgooglebot.com` is correctly rejected). |
| Redis race conditions | Rate-limit hits use a Redis `MULTI`/`EXEC` transaction around the sorted-set ZADD/ZREMRANGEBYSCORE/EXPIRE/ZCARD sequence. Challenge consumption uses the same transaction primitive for its GET+DEL. |
| Redis key explosion | Every key is namespaced, TTL'd, and built only from a hashed identity (never raw, attacker-controlled input) — see `docs/redis.md` for the exact key list. |
| DNS verification abuse | A failed trusted-bot claim is cached (briefly) too, so repeatedly claiming `Googlebot` from one IP cannot force a fresh DNS lookup on every request. |
| Request flooding | Independent, per-window sliding-rate limits (`RateAnalyzer`), each contributing to the risk score rather than causing an immediate hard block on their own. |

## Randomness

Every security-sensitive random value (challenge IDs, the PoW nonce, the
verification token's `jti`, Redis sorted-set member disambiguators) is
generated with `random_bytes()`. `rand()`, `mt_rand()`, and `uniqid()` are
never used for anything security-sensitive anywhere in this package.

## False-positive philosophy

No single weak signal causes a hard BLOCK. `RiskScoreEngine` sums
independent analyzer signals into one score and only blocks once that score
crosses a configurable threshold — see `RiskScoreEngineTest`. A suspicious
User-Agent alone, for example, contributes a modest score
(`scoring.suspicious_user_agent`, default 20) — well under the default
BLOCK threshold of 70.

## No permanent blocking

`BlockService` only ever issues temporary, escalating blocks
(`blocking.durations`, minutes), and the escalation counter itself expires
(`blocking.violation_ttl_seconds`) after a period of no further violations.
An IP is never permanently blocked, because an IP is not reliably a single
user — NAT, shared mobile carrier addresses, VPNs, and corporate proxies all
mean one IP can represent many people, and one person can rotate across many
IPs.

## Octane / long-running worker safety

No package component stores request-specific state in a static property or
a mutable singleton. Every stateful piece of information (rate counts,
block status, challenge state, DNS cache) lives in Redis or in a token the
client holds (the verification cookie), not in PHP process memory. Services
registered as container singletons (`AntiBotManager`, `RiskScoreEngine`,
etc.) are themselves stateless — every method takes the full context it
needs as an argument rather than relying on prior state set on `$this`.

## Known limitations

- Trusted-crawler verification is DNS-based only in v1 (no IP-range-file
  matching yet) — see `docs/trusted-bots.md`.
- `SystemDnsResolver`'s timeout is best-effort (PHP has no reliable
  cross-platform per-call DNS timeout).
- Proof-of-work is a friction mechanism, not a hard cryptographic
  guarantee — a sufficiently motivated attacker with real compute can solve
  it. It is designed to raise the cost of automation cheaply, not to be
  unbreakable; combine it with the risk-scoring pipeline (which it feeds
  into) rather than relying on it in isolation.
- This package does not perform invasive fingerprinting by design (see
  `docs/privacy.md`) — that is a deliberate trade-off between detection
  strength and privacy, not an oversight.

## Reporting a vulnerability

If you believe you've found a security issue in this package, please open a
private security advisory on the GitHub repository rather than a public
issue.
