<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Services;

use AlisherNPortfolio\LaravelAntiBot\Contracts\BlockStore;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\Support\Hashing;

/**
 * Temporary-only blocking with escalating durations. Never blocks
 * permanently: an IP is not always a single user (NAT, mobile carriers,
 * VPNs, corporate networks all share addresses), so escalation always
 * resets once a client stops offending for `violationTtlSeconds`.
 */
final class BlockService
{
    /**
     * @param  array{first: int, second: int, third: int, repeat: int}  $durationsMinutes
     */
    public function __construct(
        private readonly BlockStore $store,
        private readonly bool $enabled,
        private readonly array $durationsMinutes,
        private readonly int $violationTtlSeconds,
    ) {}

    public function isBlocked(AntiBotContext $context): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return $this->store->isBlocked(Hashing::shortHash($context->ip));
    }

    public function block(AntiBotContext $context): void
    {
        if (! $this->enabled) {
            return;
        }

        $key = Hashing::shortHash($context->ip);
        $violationCount = $this->store->incrementViolations($key, $this->violationTtlSeconds);

        $minutes = match (true) {
            $violationCount <= 1 => $this->durationsMinutes['first'],
            $violationCount === 2 => $this->durationsMinutes['second'],
            $violationCount === 3 => $this->durationsMinutes['third'],
            default => $this->durationsMinutes['repeat'],
        };

        $this->store->block($key, $minutes * 60);
    }
}
