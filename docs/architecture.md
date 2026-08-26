# Architecture

## Request flow

```text
HTTP Request
     │
     ▼
AntiBotMiddleware
     │
     ▼
Build AntiBotContext (framework-independent snapshot of the request)
     │
     ▼
AntiBotManager::inspect()
     │
     ├── disabled / excluded route or path?  ──────────────────► ALLOW
     │
     ▼
Temporary Block Check (BlockService)
     │
     ├── blocked ───────────────────────────────────────────────► BLOCK
     │
     ▼
Trusted Bot Manager
     │
     ├── verified (and bypass enabled) ─────────────────────────► ALLOW
     │
     ▼
Risk Score Engine (runs configured Analyzers, sums a 0-100 score)
     │
     ├── score ≤ allow_max_score        ──────────────────────► ALLOW
     ├── allow_max_score < score ≤ challenge_max_score ────────► CHALLENGE
     └── score > challenge_max_score    ──────────────────────► BLOCK
```

A Redis failure anywhere in the block-check/trusted-bot-cache/risk-analysis
path is caught once, centrally, in `AntiBotManager::inspect()`, and resolved
via `antibot.failure_strategy` (`allow` by default, or `block`). See
`docs/redis.md`.

## Layers and responsibilities

| Layer | Responsibility |
|---|---|
| `Http\Middleware\AntiBotMiddleware` | Builds context, calls the service, turns a decision into an HTTP response. |
| `Http\Controllers\ChallengeController` | Standalone `GET /challenge` and `POST /verify` endpoints. |
| `Contracts\AntiBotService` / `Services\AntiBotManager` | Public entry point; orchestrates the collaborators below. Contains no analysis logic itself. |
| `TrustedBots\TrustedBotManager` | Decides whether a request is a *verified* trusted crawler. Never trusts the User-Agent alone. |
| `Services\RiskScoreEngine` | Runs `Analyzer`s, sums a `RiskScore`, maps it to a decision. |
| `Analyzers\*` | Pure signal producers. Never decide ALLOW/CHALLENGE/BLOCK. |
| `Services\ChallengeService` / `Contracts\ChallengeProvider` | Creates and verifies challenges (proof-of-work by default). |
| `Services\VerificationService` | Issues/validates the stateless "already verified" cookie. |
| `Services\BlockService` | Temporary, escalating blocking. Never permanent. |
| `Stores\Redis*` | Redis-backed implementations of `RateLimiter`, `BlockStore`, `ChallengeStore`. |
| `Support\SystemDnsResolver` | The only place that calls PHP's real DNS functions. |

## Why trusted-bot verification happens before risk scoring

If a claimed Googlebot/Bingbot request went through `RateAnalyzer`,
`CrawlPatternAnalyzer`, etc. first, a legitimate crawler that indexes many
pages quickly would routinely be challenged or blocked — defeating the
package's core SEO-safety requirement. Verification happens first, and a
verified crawler (with the default configuration) skips the risk engine
entirely.

## Why analyzers never decide the outcome

Keeping `Analyzer::analyze()` limited to "return a score + reason" means
analyzers can be added, removed, or reordered freely (including entirely
custom ones from a host application) without any of them needing to know
about, or fight over, the final ALLOW/CHALLENGE/BLOCK decision. That
single decision lives in exactly one place: `RiskScoreEngine`.

## Extensibility points

- **Analyzers** — implement `Contracts\Analyzer`, add the class to
  `config('antibot.analyzers')`. See `docs/custom-analyzers.md`.
- **Trusted bot verifiers** — implement `Contracts\TrustedBotVerifier`, add
  the class to `config('antibot.trusted_bots.providers')`. See
  `docs/trusted-bots.md`.
- **Challenge providers** — implement `Contracts\ChallengeProvider` and bind
  it in your own service provider. See `docs/custom-challenges.md`.
- **Responses** — `Http\Middleware\AntiBotMiddleware` is not `final`; its
  `challengeResponse()`/`blockResponse()` methods are `protected` and can be
  overridden by extending the class and binding your subclass to the
  `antibot` middleware alias yourself.

## What this package deliberately does not do

- Own authentication, sessions, or authorization (Laravel's own
  infrastructure is used throughout).
- Require a database (Redis is the only required infrastructure dependency;
  database event logging is optional and off by default).
- Modify `robots.txt` or replace sitemap functionality — it is purely an
  abuse-protection layer, orthogonal to crawler policy.
- Permanently block an IP under any circumstance.
- Depend on Cloudflare, a WAF, or a third-party CAPTCHA service.
