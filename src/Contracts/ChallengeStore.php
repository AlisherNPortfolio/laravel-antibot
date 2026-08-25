<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Contracts;

use AlisherNPortfolio\LaravelAntiBot\DTO\Challenge;

interface ChallengeStore
{
    /**
     * Persist (or re-persist, e.g. after an attempt increment) a challenge.
     * Implementations must derive the storage TTL from the challenge's own
     * `expiresAt` so re-saving never extends its lifetime.
     */
    public function create(Challenge $challenge): void;

    /**
     * Look up a challenge without consuming it (safe to call repeatedly).
     */
    public function find(string $challengeId): ?Challenge;

    /**
     * Atomically fetch-and-delete a challenge so it can never be answered
     * twice, even under concurrent requests for the same challenge id.
     */
    public function consume(string $challengeId): ?Challenge;
}
