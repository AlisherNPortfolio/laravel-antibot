<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Contracts;

/**
 * Low-level temporary-block storage. Escalation policy (which duration to
 * apply on the 1st/2nd/3rd+ offense) lives in BlockService — this contract
 * only stores/reads the current block and the violation counter that
 * drives escalation.
 */
interface BlockStore
{
    public function block(string $key, int $ttlSeconds): void;

    public function isBlocked(string $key): bool;

    /**
     * Increment the violation counter for a key and return the new count.
     * The counter itself expires after `$violationTtlSeconds` of no new
     * violations, so escalation naturally resets for reformed/rotated clients.
     */
    public function incrementViolations(string $key, int $violationTtlSeconds): int;
}
