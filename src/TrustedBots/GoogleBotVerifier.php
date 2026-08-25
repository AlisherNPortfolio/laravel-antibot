<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\TrustedBots;

use AlisherNPortfolio\LaravelAntiBot\Contracts\DnsResolver;
use AlisherNPortfolio\LaravelAntiBot\Contracts\TrustedBotVerifier;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\DTO\TrustedBotResult;
use AlisherNPortfolio\LaravelAntiBot\Enums\TrustedBotType;
use AlisherNPortfolio\LaravelAntiBot\TrustedBots\Concerns\VerifiesViaDns;

/**
 * Verifies a request claiming to be Googlebot using Google's documented
 * reverse+forward DNS procedure:
 * https://developers.google.com/search/docs/crawling-indexing/verifying-googlebot
 *
 * A hostname is only trusted if it belongs to one of Google's official
 * crawler domains (googlebot.com, google.com, googleusercontent.com) AND
 * its forward DNS resolution includes the original request IP.
 *
 * Limitation: this does not consult Google's published crawler IP-range
 * JSON files (an alternative, non-DNS verification method Google also
 * documents) — DNS verification alone is used for V1. See
 * docs/trusted-bots.md.
 */
final class GoogleBotVerifier implements TrustedBotVerifier
{
    use VerifiesViaDns;

    /** @var list<string> */
    private const ALLOWED_HOSTNAME_SUFFIXES = [
        'googlebot.com',
        'google.com',
        'googleusercontent.com',
    ];

    public function __construct(
        private readonly DnsResolver $dns,
    ) {}

    public function type(): TrustedBotType
    {
        return TrustedBotType::GOOGLEBOT;
    }

    public function supports(AntiBotContext $context): bool
    {
        return str_contains(strtolower($context->userAgent), 'googlebot');
    }

    public function verify(AntiBotContext $context): TrustedBotResult
    {
        $outcome = $this->verifyHostnameOwnership($this->dns, $context->ip, self::ALLOWED_HOSTNAME_SUFFIXES);

        if (! $outcome['verified']) {
            return TrustedBotResult::notVerified($outcome['reason']);
        }

        return TrustedBotResult::verified(
            type: TrustedBotType::GOOGLEBOT,
            reason: 'dns_verified',
            metadata: ['hostname' => $outcome['hostname']],
        );
    }
}
