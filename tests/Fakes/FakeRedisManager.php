<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Tests\Fakes;

/**
 * Stand-in for Illuminate\Redis\RedisManager, bound to the container's
 * `redis` key in tests. Every connection name resolves to the same
 * shared in-memory connection, matching how the package always talks to
 * a single logical Redis instance.
 */
final class FakeRedisManager
{
    public function __construct(
        private readonly ArrayRedisConnection $connection,
    ) {}

    public function connection(?string $name = null): ArrayRedisConnection
    {
        return $this->connection;
    }
}
