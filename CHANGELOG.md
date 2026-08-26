# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
