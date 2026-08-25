<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\TrustedBots;

use AlisherNPortfolio\LaravelAntiBot\Contracts\TrustedBotVerifier;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\DTO\TrustedBotResult;
use AlisherNPortfolio\LaravelAntiBot\Enums\TrustedBotType;
use AlisherNPortfolio\LaravelAntiBot\Support\Hashing;
use Illuminate\Support\Facades\Redis;
use JsonException;
use Throwable;

/**
 * Decides whether a request is a verified trusted crawler, before any
 * risk scoring runs. Never trusts the claimed User-Agent by itself —
 * only a verifier's network-verified result counts.
 *
 * Successful (and, briefly, failed) verifications are cached so DNS
 * lookups only happen occasionally per IP rather than on every request.
 * A Redis outage while reading/writing this cache degrades to a fresh
 * DNS check on every request rather than ever failing the request.
 */
final class TrustedBotManager
{
    /**
     * @param  list<TrustedBotVerifier>  $verifiers
     */
    public function __construct(
        private readonly array $verifiers,
        private readonly bool $enabled,
        private readonly bool $cacheEnabled,
        private readonly int $cacheTtlSeconds,
        private readonly int $negativeCacheTtlSeconds,
        private readonly string $redisConnection,
        private readonly string $keyPrefix,
    ) {}

    public function check(AntiBotContext $context): TrustedBotResult
    {
        if (! $this->enabled) {
            return TrustedBotResult::notVerified('trusted_bots_disabled');
        }

        $verifier = $this->findSupportingVerifier($context);

        if ($verifier === null) {
            return TrustedBotResult::notVerified('no_trusted_bot_claim');
        }

        $cacheKey = $this->cacheKey($verifier->type()->value, $context->ip);

        $cached = $this->readCache($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $result = $verifier->verify($context);

        $this->writeCache($cacheKey, $result);

        return $result;
    }

    private function findSupportingVerifier(AntiBotContext $context): ?TrustedBotVerifier
    {
        foreach ($this->verifiers as $verifier) {
            if ($verifier->supports($context)) {
                return $verifier;
            }
        }

        return null;
    }

    private function cacheKey(string $type, string $ip): string
    {
        return $this->keyPrefix.':trusted-bot:'.$type.':'.Hashing::shortHash($ip);
    }

    private function readCache(string $cacheKey): ?TrustedBotResult
    {
        if (! $this->cacheEnabled) {
            return null;
        }

        try {
            $raw = Redis::connection($this->redisConnection)->get($cacheKey);
        } catch (Throwable) {
            return null;
        }

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        if (($data['verified'] ?? false) === true && isset($data['type']) && is_string($data['type'])) {
            $type = TrustedBotType::tryFrom($data['type']);

            // A cached value from a type that no longer exists (e.g. after a
            // package upgrade) is treated as a cache miss, never a fatal error.
            if ($type === null) {
                return null;
            }

            return TrustedBotResult::verified(
                $type,
                reason: 'cached_verification',
                metadata: ['hostname' => $data['hostname'] ?? null],
            );
        }

        return TrustedBotResult::notVerified('cached_negative_verification');
    }

    private function writeCache(string $cacheKey, TrustedBotResult $result): void
    {
        if (! $this->cacheEnabled) {
            return;
        }

        $ttl = $result->verified ? $this->cacheTtlSeconds : $this->negativeCacheTtlSeconds;

        if ($ttl <= 0) {
            return;
        }

        $payload = json_encode([
            'verified' => $result->verified,
            'type' => $result->type?->value,
            'hostname' => $result->metadata['hostname'] ?? null,
        ], JSON_THROW_ON_ERROR);

        try {
            Redis::connection($this->redisConnection)->setex($cacheKey, $ttl, $payload);
        } catch (Throwable) {
            // Best-effort cache write only; the caller already has the verification result.
        }
    }
}
