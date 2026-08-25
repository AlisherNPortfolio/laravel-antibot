<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Services;

use AlisherNPortfolio\LaravelAntiBot\Contracts\ChallengeProvider;
use AlisherNPortfolio\LaravelAntiBot\Contracts\ChallengeStore;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\DTO\Challenge;
use AlisherNPortfolio\LaravelAntiBot\Support\Hashing;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates challenge creation and verification. Every unsuccessful
 * verify() attempt (wrong answer, expired/unknown challenge, or a replay
 * of an already-consumed challenge) counts against the client's failure
 * tracker, which both escalates future risk scores (ChallengeAnalyzer)
 * and, after `maxAttempts`, burns the challenge outright.
 */
final class ChallengeService
{
    public function __construct(
        private readonly ChallengeProvider $provider,
        private readonly ChallengeStore $store,
        private readonly ChallengeFailureTracker $failureTracker,
        private readonly int $defaultDifficulty,
        private readonly bool $adaptiveDifficulty,
        private readonly int $maxAttempts,
        private readonly int $failureTrackingTtlSeconds,
        private readonly bool $loggingEnabled,
    ) {}

    public function create(AntiBotContext $context, ?int $riskScore = null): Challenge
    {
        $challenge = $this->provider->create($context, $this->resolveDifficulty($riskScore));
        $this->store->create($challenge);

        $this->log('antibot.challenge_created', $context, ['difficulty' => $challenge->difficulty]);

        return $challenge;
    }

    public function verify(string $challengeId, string $answer, AntiBotContext $context): bool
    {
        $clientKey = Hashing::shortHash($context->ip);
        $challenge = $this->store->find($challengeId);

        if ($challenge === null || $challenge->isExpired()) {
            $this->failureTracker->recordFailure($clientKey, $this->failureTrackingTtlSeconds);
            $this->log('antibot.challenge_failed', $context, ['cause' => 'expired_or_unknown']);

            return false;
        }

        if (! $this->provider->verify($challenge, $answer)) {
            $this->handleWrongAnswer($challenge, $clientKey);
            $this->log('antibot.challenge_failed', $context, ['cause' => 'wrong_answer']);

            return false;
        }

        // Atomic fetch-and-delete: guarantees a correct answer can only ever succeed once.
        if ($this->store->consume($challengeId) === null) {
            $this->failureTracker->recordFailure($clientKey, $this->failureTrackingTtlSeconds);
            $this->log('antibot.challenge_failed', $context, ['cause' => 'replayed']);

            return false;
        }

        $this->failureTracker->recordSuccess($clientKey);
        $this->log('antibot.challenge_verified', $context);

        return true;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function log(string $event, AntiBotContext $context, array $extra = []): void
    {
        if (! $this->loggingEnabled) {
            return;
        }

        Log::info($event, array_merge([
            'ip_hash' => Hashing::shortHash($context->ip),
            'path' => $context->path,
        ], $extra));
    }

    private function handleWrongAnswer(Challenge $challenge, string $clientKey): void
    {
        $this->failureTracker->recordFailure($clientKey, $this->failureTrackingTtlSeconds);

        if ($challenge->attempts + 1 >= $this->maxAttempts) {
            $this->store->consume($challenge->id);

            return;
        }

        $this->store->create($challenge->withIncrementedAttempts());
    }

    private function resolveDifficulty(?int $riskScore): int
    {
        if (! $this->adaptiveDifficulty || $riskScore === null) {
            return $this->defaultDifficulty;
        }

        $bonusBits = intdiv($riskScore, 10);

        return min($this->defaultDifficulty + 8, $this->defaultDifficulty + $bonusBits);
    }
}
