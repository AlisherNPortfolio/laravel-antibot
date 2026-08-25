<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Stores;

use AlisherNPortfolio\LaravelAntiBot\Contracts\ChallengeStore;
use AlisherNPortfolio\LaravelAntiBot\DTO\Challenge;
use AlisherNPortfolio\LaravelAntiBot\Stores\Concerns\InteractsWithRedis;
use JsonException;

final class RedisChallengeStore implements ChallengeStore
{
    use InteractsWithRedis;

    public function __construct(
        private readonly string $redisConnection,
        private readonly string $keyPrefix,
    ) {}

    public function create(Challenge $challenge): void
    {
        $this->guarded(function ($redis) use ($challenge) {
            $ttl = max(1, $challenge->expiresAt - time());
            $redis->setex($this->key("challenge:{$challenge->id}"), $ttl, $this->serialize($challenge));
        });
    }

    public function find(string $challengeId): ?Challenge
    {
        $json = $this->guarded(
            fn ($redis) => $redis->get($this->key("challenge:{$challengeId}"))
        );

        return $json ? $this->deserialize($json) : null;
    }

    public function consume(string $challengeId): ?Challenge
    {
        $redisKey = $this->key("challenge:{$challengeId}");

        // GET + DEL inside a single Redis transaction: atomic fetch-and-delete
        // without relying on Redis 6.2's GETDEL (portable across older Redis
        // and across the phpredis/predis client drivers).
        $json = $this->guarded(function ($redis) use ($redisKey) {
            $results = $redis->transaction(function ($tx) use ($redisKey) {
                $tx->get($redisKey);
                $tx->del($redisKey);
            });

            return $results[0] ?? null;
        });

        return $json ? $this->deserialize($json) : null;
    }

    private function serialize(Challenge $challenge): string
    {
        return json_encode([
            'id' => $challenge->id,
            'nonce' => $challenge->nonce,
            'difficulty' => $challenge->difficulty,
            'expires_at' => $challenge->expiresAt,
            'attempts' => $challenge->attempts,
            'context_hash' => $challenge->contextHash,
        ], JSON_THROW_ON_ERROR);
    }

    private function deserialize(string $json): ?Challenge
    {
        try {
            /** @var array{id:string,nonce:string,difficulty:int,expires_at:int,attempts:int,context_hash:?string} $data */
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return new Challenge(
            id: $data['id'],
            nonce: $data['nonce'],
            difficulty: $data['difficulty'],
            expiresAt: $data['expires_at'],
            attempts: $data['attempts'] ?? 0,
            contextHash: $data['context_hash'] ?? null,
        );
    }
}
