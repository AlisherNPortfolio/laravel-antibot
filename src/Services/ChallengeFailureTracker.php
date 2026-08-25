<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Services;

use AlisherNPortfolio\LaravelAntiBot\Stores\Concerns\InteractsWithRedis;

/**
 * Tracks per-client challenge failure counts so ChallengeAnalyzer can turn
 * repeated failures into risk score, and a success can clear the slate.
 * Deliberately separate from BlockStore: this counter drives risk scoring
 * (a signal), not the temporary-block escalation policy (a decision).
 */
final class ChallengeFailureTracker
{
    use InteractsWithRedis;

    public function __construct(
        private readonly string $redisConnection,
        private readonly string $keyPrefix,
    ) {}

    public function recordFailure(string $clientKey, int $ttlSeconds): int
    {
        return $this->guarded(function ($redis) use ($clientKey, $ttlSeconds) {
            $redisKey = $this->key("challenge-failures:{$clientKey}");
            $count = (int) $redis->incr($redisKey);
            $redis->expire($redisKey, max(1, $ttlSeconds));

            return $count;
        });
    }

    public function recordSuccess(string $clientKey): void
    {
        $this->guarded(function ($redis) use ($clientKey) {
            $redis->del($this->key("challenge-failures:{$clientKey}"));
        });
    }

    public function failureCount(string $clientKey): int
    {
        return (int) $this->guarded(
            fn ($redis) => $redis->get($this->key("challenge-failures:{$clientKey}"))
        );
    }
}
