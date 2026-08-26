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
 * Verifies a request claiming to be YandexBot using Yandex's documented
 * reverse+forward DNS procedure:
 * https://yandex.com/support/webmaster/robot-workings/check-robot.html
 *
 * A hostname is only trusted if it ends in one of Yandex's official crawler
 * domains (yandex.ru, yandex.net, yandex.com) AND its forward DNS
 * resolution includes the original request IP.
 *
 * Limitation: this does not consult any IP-range list — DNS verification
 * alone is used for V1, matching GoogleBotVerifier/BingBotVerifier. See
 * docs/trusted-bots.md.
 */
final class YandexBotVerifier implements TrustedBotVerifier
{
    use VerifiesViaDns;

    /** @var list<string> */
    private const ALLOWED_HOSTNAME_SUFFIXES = [
        'yandex.ru',
        'yandex.net',
        'yandex.com',
    ];

    public function __construct(
        private readonly DnsResolver $dns,
    ) {}

    public function type(): TrustedBotType
    {
        return TrustedBotType::YANDEXBOT;
    }

    public function supports(AntiBotContext $context): bool
    {
        return str_contains(strtolower($context->userAgent), 'yandexbot');
    }

    public function verify(AntiBotContext $context): TrustedBotResult
    {
        $outcome = $this->verifyHostnameOwnership($this->dns, $context->ip, self::ALLOWED_HOSTNAME_SUFFIXES);

        if (! $outcome['verified']) {
            return TrustedBotResult::notVerified($outcome['reason']);
        }

        return TrustedBotResult::verified(
            type: TrustedBotType::YANDEXBOT,
            reason: 'dns_verified',
            metadata: ['hostname' => $outcome['hostname']],
        );
    }
}
