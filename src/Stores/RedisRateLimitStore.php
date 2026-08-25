<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Stores;

use AlisherNPortfolio\LaravelAntiBot\Contracts\RateLimiter;
use AlisherNPortfolio\LaravelAntiBot\Stores\Concerns\InteractsWithRedis;

/**
 * True sliding-window rate limiter backed by a Redis sorted set: each hit
 * is scored by its microtime, expired members are trimmed on every call,
 * and the whole read-modify-write sequence runs inside a Redis
 * transaction (MULTI/EXEC) so concurrent requests never corrupt the count.
 */
final class RedisRateLimitStore implements RateLimiter
{
    use InteractsWithRedis;

    public function __construct(
        private readonly string $redisConnection,
        private readonly string $keyPrefix,
    ) {}

    public function hit(string $key, int $windowSeconds): int
    {
        $windowSeconds = max(1, $windowSeconds);

        return $this->guarded(function ($redis) use ($key, $windowSeconds) {
            $redisKey = $this->key("rate:{$key}:{$windowSeconds}");
            $now = microtime(true);
            $member = $now.'-'.bin2hex(random_bytes(4));
            $cutoff = $now - $windowSeconds;

            $results = $redis->transaction(function ($tx) use ($redisKey, $now, $member, $cutoff, $windowSeconds) {
                $tx->zadd($redisKey, $now, $member);
                $tx->zremrangebyscore($redisKey, '-inf', $cutoff);
                $tx->expire($redisKey, $windowSeconds + 1);
                $tx->zcard($redisKey);
            });

            return (int) end($results);
        });
    }
}
