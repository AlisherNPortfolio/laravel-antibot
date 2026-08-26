<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Analyzers\ChallengeAnalyzer;
use AlisherNPortfolio\LaravelAntiBot\Analyzers\CrawlPatternAnalyzer;
use AlisherNPortfolio\LaravelAntiBot\Analyzers\RateAnalyzer;
use AlisherNPortfolio\LaravelAntiBot\Analyzers\UserAgentAnalyzer;
use AlisherNPortfolio\LaravelAntiBot\TrustedBots\BingBotVerifier;
use AlisherNPortfolio\LaravelAntiBot\TrustedBots\GoogleBotVerifier;
use AlisherNPortfolio\LaravelAntiBot\TrustedBots\YandexBotVerifier;

return [

    // Master switch. When false, the middleware passes every request
    // through untouched and no Redis/DNS operation is performed at all.
    'enabled' => env('ANTIBOT_ENABLED', true),

    'middleware' => [
        'alias' => 'antibot',
    ],

    // Analyzers run (in order) by RiskScoreEngine. Add your own by
    // implementing AlisherNPortfolio\LaravelAntiBot\Contracts\Analyzer
    // and appending its class name here.
    'analyzers' => [
        RateAnalyzer::class,
        UserAgentAnalyzer::class,
        CrawlPatternAnalyzer::class,
        ChallengeAnalyzer::class,
    ],

    // Final score (0-100) is mapped to a decision by these thresholds:
    // score <= allow_max_score            -> ALLOW
    // allow_max_score < score <= challenge_max_score -> CHALLENGE
    // score > challenge_max_score         -> BLOCK
    'decision' => [
        'allow_max_score' => 30,
        'challenge_max_score' => 70,
    ],

    // The "already passed a challenge" cookie. Stateless and
    // authenticated-encrypted; not tied to IP or server-side session.
    'verification' => [
        'cookie_name' => 'antibot_verified',
        'ttl_minutes' => 30,
        'secure' => env('ANTIBOT_VERIFICATION_COOKIE_SECURE', true),
        'same_site' => 'lax',
    ],

    'challenge' => [
        'enabled' => true,
        // How long a browser has to solve a single challenge.
        'challenge_ttl_seconds' => 60,
        // Leading zero *bits* the client-found SHA-256 hash must have.
        'default_difficulty' => 16,
        // Scale difficulty up slightly for higher-risk clients (capped +8 bits).
        'adaptive_difficulty' => true,
        'max_attempts' => 3,
        // How long a client's failure count is remembered for risk scoring.
        'failure_tracking_ttl_seconds' => 900,
        // Throttles applied directly to the package's own routes, since
        // they are intentionally excluded from the antibot middleware
        // itself (to avoid challenging the challenge endpoint).
        'route_rate_limit' => '60,1',
        'verify_rate_limit' => '20,1',
    ],

    // Sliding-window request volume limits. Each window is independent:
    // a short aggressive burst and a sustained elevated rate are scored
    // separately.
    'rate_limits' => [
        '10_seconds' => ['seconds' => 10, 'limit' => 20, 'score' => 40],
        '1_minute' => ['seconds' => 60, 'limit' => 100, 'score' => 30],
        '5_minutes' => ['seconds' => 300, 'limit' => 300, 'score' => 20],
    ],

    'user_agent' => [
        // Substrings (case-insensitive) that mark a client as a generic
        // scripted HTTP client. This alone never causes a hard block.
        'suspicious_patterns' => [
            'curl',
            'wget',
            'python-requests',
            'python-urllib',
            'scrapy',
            'go-http-client',
            'java/',
            'libwww',
            'httpclient',
            'httpunit',
        ],
    ],

    'crawling' => [
        'enabled' => true,
        'window_seconds' => 60,
        // Distinct paths visited within the window before it's flagged as broad crawling.
        'max_paths' => 100,
        // Below max_paths, this pair detects sequential enumeration: many
        // distinct raw paths collapsing onto very few normalized shapes
        // (e.g. /articles/1, /articles/2, ... all become /articles/{n}).
        'enumeration_min_requests' => 10,
        'enumeration_ratio_threshold' => 5,
    ],

    'scoring' => [
        'suspicious_user_agent' => 20,
        'missing_user_agent' => 15,
        // A User-Agent claiming Googlebot/Bingbot that already failed
        // TrustedBotManager's DNS verification — a likely spoofing attempt.
        'spoofed_trusted_bot_claim' => 40,
        'rapid_crawling' => 30,
        'challenge_failure_1' => 20,
        'challenge_failure_2' => 30,
        'challenge_failure_3_plus' => 50,
        // Negative: a currently-valid verification cookie reduces risk.
        'successful_verification' => -30,
    ],

    'blocking' => [
        'enabled' => true,
        // Escalating temporary block durations, in minutes. Never permanent —
        // an IP is not always a single user (NAT, mobile, VPN, proxies).
        'durations' => [
            'first' => 5,
            'second' => 30,
            'third' => 60,
            'repeat' => 360,
        ],
        // How long the escalation (violation) counter is remembered.
        // Once a client stays quiet longer than this, escalation resets.
        'violation_ttl_seconds' => 86400,
    ],

    'trusted_bots' => [
        'enabled' => true,

        // Whether a verified crawler skips AntiBot's challenge/risk-scoring
        // and temporary-block logic. Does NOT affect infrastructure-level
        // rate limiting (e.g. Nginx) the host application applies separately.
        'bypass_challenge' => true,
        'bypass_block' => true,

        // Verifiers are resolved through the container — add your own by
        // implementing TrustedBotVerifier and appending its class name.
        'providers' => [
            GoogleBotVerifier::class,
            BingBotVerifier::class,
            YandexBotVerifier::class,
        ],

        'cache' => [
            'enabled' => true,
            // TTL for a *successful* verification.
            'ttl_seconds' => 3600,
            // Short TTL for a *failed* claim, to blunt DNS-lookup abuse
            // from a persistent spoofed User-Agent without permanently
            // penalizing a rotated/dynamic IP that may be legitimate later.
            'negative_ttl_seconds' => 30,
        ],

        'verification' => [
            // Best-effort only — see SystemDnsResolver docblock and
            // docs/trusted-bots.md for platform timeout limitations.
            'dns_timeout_seconds' => 2,
        ],
    ],

    // Purely diagnostic, opt-in observability for known social/chat
    // link-preview fetchers (Telegram, Facebook, Twitter/X, Slack, Discord,
    // ...). These clients cannot run JavaScript, so if one of them is ever
    // CHALLENGE'd or BLOCK'd, the site's link preview (title/image) breaks
    // for that platform. This performs NO bypass and never changes a
    // decision — enabling it only logs a distinct
    // `antibot.link_preview_bot_affected` event so you can notice the
    // pattern and, if appropriate, add the affected routes to
    // `exclude.routes`/`exclude.paths` below. See docs/trusted-bots.md.
    'link_preview_bots' => [
        'log_when_affected' => env('ANTIBOT_LOG_LINK_PREVIEW_BOTS', false),

        'patterns' => [
            'telegrambot',
            'facebookexternalhit',
            'twitterbot',
            'slackbot',
            'discordbot',
            'whatsapp',
            'linkedinbot',
            'skypeuripreview',
            'redditbot',
            'vkshare',
            'pinterest',
        ],
    ],

    // Routes/route-names never subject to AntiBot at all.
    'exclude' => [
        'routes' => [],
        'paths' => [],
    ],

    'logging' => [
        'enabled' => true,
        // Optional: also persist a row per decision to the anti_bot_events
        // table (see database/migrations). Never required for core operation.
        'store_database_events' => false,
    ],

    'redis' => [
        'connection' => env('ANTIBOT_REDIS_CONNECTION', 'default'),
        'prefix' => 'antibot',
    ],

    // What happens when Redis is unreachable: 'allow' (default, fail-open
    // so an outage never takes the site down) or 'block' (fail-closed).
    'failure_strategy' => env('ANTIBOT_FAILURE_STRATEGY', 'allow'),

    'routes' => [
        'enabled' => true,
        'prefix' => 'anti-bot',
        'middleware' => ['web'],
    ],

];
