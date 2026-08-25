<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Stores\Concerns;

use AlisherNPortfolio\LaravelAntiBot\Support\Exceptions\AntiBotStoreException;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Requires the using class to declare `private readonly string $redisConnection`
 * and `private readonly string $keyPrefix` (typically via constructor
 * property promotion) — the trait deliberately does not provide its own
 * constructor so each consumer stays free to accept whatever additional
 * dependencies it needs.
 *
 * Deliberately untyped return/parameter for the Redis connection itself
 * (rather than `Illuminate\Redis\Connections\Connection`): this lets
 * tests bind a lightweight in-memory fake in place of the `redis`
 * container binding without needing to extend Laravel's real connection
 * classes. Both expose the same Redis command methods used here
 * (get/setex/del/exists/incr/expire/sadd/scard/zadd/zremrangebyscore/zcard/transaction).
 */
trait InteractsWithRedis
{
    protected function connection(): mixed
    {
        return Redis::connection($this->redisConnection);
    }

    protected function key(string $suffix): string
    {
        return $this->keyPrefix.':'.$suffix;
    }

    /**
     * Run a Redis operation, converting any failure (connection refused,
     * timeout, protocol error, ...) into a single package-level exception
     * so callers only ever need to handle one failure type.
     *
     * @template TReturn
     *
     * @param  callable(mixed): TReturn  $operation
     * @return TReturn
     */
    protected function guarded(callable $operation): mixed
    {
        try {
            return $operation($this->connection());
        } catch (Throwable $e) {
            throw new AntiBotStoreException(
                "AntiBot Redis operation failed: {$e->getMessage()}",
                previous: $e,
            );
        }
    }
}
