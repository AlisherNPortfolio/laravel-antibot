<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Stores;

use AlisherNPortfolio\LaravelAntiBot\Contracts\BlockStore;
use AlisherNPortfolio\LaravelAntiBot\Stores\Concerns\InteractsWithRedis;

final class RedisBlockStore implements BlockStore
{
    use InteractsWithRedis;

    public function __construct(
        private readonly string $redisConnection,
        private readonly string $keyPrefix,
    ) {}

    public function block(string $key, int $ttlSeconds): void
    {
        $this->guarded(function ($redis) use ($key, $ttlSeconds) {
            $redis->setex($this->key("block:{$key}"), max(1, $ttlSeconds), '1');
        });
    }

    public function isBlocked(string $key): bool
    {
        return (bool) $this->guarded(
            fn ($redis) => $redis->exists($this->key("block:{$key}"))
        );
    }

    public function incrementViolations(string $key, int $violationTtlSeconds): int
    {
        return $this->guarded(function ($redis) use ($key, $violationTtlSeconds) {
            $redisKey = $this->key("violations:{$key}");
            $count = (int) $redis->incr($redisKey);
            $redis->expire($redisKey, max(1, $violationTtlSeconds));

            return $count;
        });
    }
}
