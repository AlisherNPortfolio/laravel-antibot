<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\DTO;

/**
 * A single proof-of-work (or other) challenge instance.
 *
 * `$expiresAt` is an absolute unix timestamp rather than a TTL so that a
 * {@see \AlisherNPortfolio\LaravelAntiBot\Contracts\ChallengeStore} can
 * re-persist an updated attempt count without resetting the original
 * expiry window.
 */
final readonly class Challenge
{
    public function __construct(
        public string $id,
        public string $nonce,
        public int $difficulty,
        public int $expiresAt,
        public int $attempts = 0,
        public ?string $contextHash = null,
    ) {}

    public function isExpired(?int $now = null): bool
    {
        return ($now ?? time()) >= $this->expiresAt;
    }

    public function withIncrementedAttempts(): self
    {
        return new self(
            id: $this->id,
            nonce: $this->nonce,
            difficulty: $this->difficulty,
            expiresAt: $this->expiresAt,
            attempts: $this->attempts + 1,
            contextHash: $this->contextHash,
        );
    }
}
