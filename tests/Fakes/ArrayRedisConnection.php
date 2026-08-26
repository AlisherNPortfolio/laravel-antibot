<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Tests\Fakes;

/**
 * Minimal in-memory stand-in for a real Redis connection, implementing
 * only the handful of commands the package actually issues (string,
 * set, sorted-set and MULTI/EXEC-style transaction semantics). Bound in
 * place of the `redis` container entry so tests never require a running
 * Redis server. Not a full Redis emulation — TTLs are recorded but not
 * actively swept, which the package's own components never rely on for
 * correctness (expiry is always re-checked at the application layer too).
 */
final class ArrayRedisConnection
{
    /** @var array<string, array{value: mixed, expires_at: ?int}> */
    private array $store = [];

    /** @var list<mixed>|null */
    private ?array $txResults = null;

    public function get(string $key): ?string
    {
        $value = $this->store[$key]['value'] ?? null;

        return $this->record(is_string($value) ? $value : null);
    }

    public function setex(string $key, int $ttl, string $value): bool
    {
        $this->store[$key] = ['value' => $value, 'expires_at' => time() + $ttl];

        return $this->record(true);
    }

    public function set(string $key, string $value): bool
    {
        $this->store[$key] = ['value' => $value, 'expires_at' => null];

        return $this->record(true);
    }

    public function del(string ...$keys): int
    {
        $count = 0;

        foreach ($keys as $key) {
            if (array_key_exists($key, $this->store)) {
                unset($this->store[$key]);
                $count++;
            }
        }

        return $this->record($count);
    }

    public function exists(string $key): int
    {
        return $this->record(array_key_exists($key, $this->store) ? 1 : 0);
    }

    public function incr(string $key): int
    {
        $current = (int) ($this->store[$key]['value'] ?? 0);
        $current++;
        $this->store[$key] = ['value' => (string) $current, 'expires_at' => $this->store[$key]['expires_at'] ?? null];

        return $this->record($current);
    }

    public function expire(string $key, int $ttl): bool
    {
        if (array_key_exists($key, $this->store)) {
            $this->store[$key]['expires_at'] = time() + $ttl;
        }

        return $this->record(true);
    }

    public function ttl(string $key): int
    {
        $expiresAt = $this->store[$key]['expires_at'] ?? null;

        return $this->record($expiresAt === null ? -1 : max(0, $expiresAt - time()));
    }

    public function sadd(string $key, string $member): int
    {
        /** @var list<string> $set */
        $set = $this->store[$key]['value'] ?? [];
        $added = 0;

        if (! in_array($member, $set, true)) {
            $set[] = $member;
            $added = 1;
        }

        $this->store[$key] = ['value' => $set, 'expires_at' => $this->store[$key]['expires_at'] ?? null];

        return $this->record($added);
    }

    public function scard(string $key): int
    {
        /** @var list<string> $set */
        $set = $this->store[$key]['value'] ?? [];

        return $this->record(count($set));
    }

    public function zadd(string $key, float $score, string $member): int
    {
        /** @var array<string, float> $zset */
        $zset = $this->store[$key]['value'] ?? [];
        $isNew = ! array_key_exists($member, $zset);
        $zset[$member] = $score;
        $this->store[$key] = ['value' => $zset, 'expires_at' => $this->store[$key]['expires_at'] ?? null];

        return $this->record($isNew ? 1 : 0);
    }

    /**
     * `string $min`/`string $max` deliberately mirrors the real phpredis
     * extension's arginfo (it types these `string` to allow the
     * "-inf"/"+inf"/"(exclusive" range syntax) rather than accepting a
     * looser `string|float` union — under `strict_types`, passing a raw
     * float here is a real TypeError against real Redis that this fake
     * would otherwise silently tolerate.
     */
    public function zremrangebyscore(string $key, string $min, string $max): int
    {
        /** @var array<string, float> $zset */
        $zset = $this->store[$key]['value'] ?? [];
        $minValue = $min === '-inf' ? -INF : (float) $min;
        $maxValue = $max === '+inf' ? INF : (float) $max;
        $removed = 0;

        foreach ($zset as $member => $score) {
            if ($score >= $minValue && $score <= $maxValue) {
                unset($zset[$member]);
                $removed++;
            }
        }

        $this->store[$key]['value'] = $zset;

        return $this->record($removed);
    }

    public function zcard(string $key): int
    {
        /** @var array<string, float> $zset */
        $zset = $this->store[$key]['value'] ?? [];

        return $this->record(count($zset));
    }

    /**
     * @param  callable(self): void  $callback
     * @return list<mixed>
     */
    public function transaction(callable $callback): array
    {
        $this->txResults = [];
        $callback($this);
        $results = $this->txResults;
        $this->txResults = null;

        return $results;
    }

    private function record(mixed $result): mixed
    {
        if ($this->txResults !== null) {
            $this->txResults[] = $result;
        }

        return $result;
    }

    public function flush(): void
    {
        $this->store = [];
    }
}
