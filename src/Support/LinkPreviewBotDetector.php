<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Support;

/**
 * Recognizes well-known social/chat link-preview fetchers (Telegram,
 * Facebook, Twitter/X, Slack, Discord, ...) purely for diagnostic logging —
 * see AntiBotManager and docs/trusted-bots.md ("Social link-preview bots").
 *
 * This is deliberately NOT a TrustedBotVerifier: none of these fetchers
 * offer a network-verifiable identity (no documented reverse/forward DNS or
 * official IP-range procedure, unlike Google/Bing/Yandex), so a User-Agent
 * match here must never grant a bypass — see Architecture.md §4 and §23
 * ("never a general whitelist"). It only labels a request for observability.
 */
final class LinkPreviewBotDetector
{
    /**
     * @param  list<string>  $patterns
     */
    public function __construct(
        private readonly array $patterns,
    ) {}

    public function matches(string $userAgent): ?string
    {
        $lowerUserAgent = strtolower($userAgent);

        foreach ($this->patterns as $pattern) {
            if (str_contains($lowerUserAgent, strtolower($pattern))) {
                return $pattern;
            }
        }

        return null;
    }
}
