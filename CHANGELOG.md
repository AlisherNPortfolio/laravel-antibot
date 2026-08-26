# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- CI: the `test` matrix's PHP 8.2/8.3/8.4 × Laravel 11.* jobs were all failing dependency resolution — Composer's install-time security-advisory audit excludes any package version affected by a known advisory from the solver entirely, and every currently-released `laravel/framework` 11.x version (up to v11.56.1) has at least one open advisory. `audit.block-insecure` is now disabled for this CI-only compatibility matrix (`.github/workflows/tests.yml`); confirmed the package's own test suite passes unmodified against Laravel 11.56.1 once dependency resolution succeeds.
- CI: Laravel 13.* was removed from the test matrix — `pestphp/pest-plugin-laravel` (a dev/test-only dependency) has no released version yet that supports Laravel 13, so no combination could resolve. This is a test-tooling gap, not a known incompatibility in this package's own code; `13.*` will be re-added to the matrix once pest-plugin-laravel supports it.

## [1.0.0] - 2026-08-26

### Added

- Initial release of the package architecture:
  - Risk-based scoring pipeline (`RateAnalyzer`, `UserAgentAnalyzer`, `CrawlPatternAnalyzer`, `ChallengeAnalyzer`) via `RiskScoreEngine`.
  - Sliding-window Redis rate limiting.
  - Browser proof-of-work challenge system (`ProofOfWorkChallengeProvider`) with single-use, replay-protected challenges.
  - Stateless, authenticated-encrypted verification cookie (`VerificationService`).
  - Temporary, escalating IP blocking (`BlockService`) — never permanent.
  - Network-verified trusted crawler support for Googlebot, Bingbot, and YandexBot via reverse+forward DNS confirmation (`TrustedBotManager`, `GoogleBotVerifier`, `BingBotVerifier`, `YandexBotVerifier`), with a pluggable `DnsResolver` abstraction.
  - `AntiBotMiddleware` and package routes (`GET /anti-bot/challenge`, `POST /anti-bot/verify`).
  - Optional database event logging (`anti_bot_events` migration), disabled by default.
  - Full configuration file (`config/antibot.php`) covering scoring, rate limits, blocking, trusted bots, and exclusions.
  - Extensibility points for custom analyzers, trusted bot verifiers, and challenge providers via the container.
  - Opt-in diagnostic logging for social link-preview bots (Telegram, Facebook, ...) affected by a CHALLENGE/BLOCK decision (`LinkPreviewBotDetector`, `ANTIBOT_LOG_LINK_PREVIEW_BOTS`, default off) — logs only, never bypasses.

### Fixed

- `UserAgentAnalyzer` only recognized `googlebot`/`bingbot` for the `spoofed_trusted_bot_claim` signal, so a spoofed `YandexBot` claim (one that failed DNS verification) fell through as an ordinary, unscored User-Agent instead of being flagged as a likely spoof. The claimed-name list is now configuration-driven (`antibot.user_agent.trusted_bot_claim_patterns`, default `['googlebot', 'bingbot', 'yandexbot']`) so it stays in sync with `trusted_bots.providers` going forward.
