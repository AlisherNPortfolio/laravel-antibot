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
 * Verifies a request claiming to be Bingbot using Microsoft's documented
 * reverse+forward DNS procedure:
 * https://blogs.bing.com/webmaster/August-2012/How-to-Verify-that-Bingbot-is-Bingbot/
 *
 * A hostname is only trusted if it ends in `search.msn.com` AND its
 * forward DNS resolution includes the original request IP.
 *
 * Limitation: Microsoft also offers https://www.bing.com/toolbox/bingbot.json
 * (an IP-range list) and an online verification tool as alternatives — V1
 * implements DNS verification only. See docs/trusted-bots.md.
 */
final class BingBotVerifier implements TrustedBotVerifier
{
    use VerifiesViaDns;

    /** @var list<string> */
    private const ALLOWED_HOSTNAME_SUFFIXES = [
        'search.msn.com',
    ];

    public function __construct(
        private readonly DnsResolver $dns,
    ) {}

    public function type(): TrustedBotType
    {
        return TrustedBotType::BINGBOT;
    }

    public function supports(AntiBotContext $context): bool
    {
        return str_contains(strtolower($context->userAgent), 'bingbot');
    }

    public function verify(AntiBotContext $context): TrustedBotResult
    {
        $outcome = $this->verifyHostnameOwnership($this->dns, $context->ip, self::ALLOWED_HOSTNAME_SUFFIXES);

        if (! $outcome['verified']) {
            return TrustedBotResult::notVerified($outcome['reason']);
        }

        return TrustedBotResult::verified(
            type: TrustedBotType::BINGBOT,
            reason: 'dns_verified',
            metadata: ['hostname' => $outcome['hostname']],
        );
    }
}
