<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Providers\ProofOfWorkChallengeProvider;
use AlisherNPortfolio\LaravelAntiBot\Services\ChallengeFailureTracker;
use AlisherNPortfolio\LaravelAntiBot\Services\ChallengeService;
use AlisherNPortfolio\LaravelAntiBot\Stores\RedisChallengeStore;

beforeEach(function () {
    $this->fakeRedis();
});

function makeChallengeService(int $maxAttempts = 3, int $difficulty = 16): ChallengeService
{
    return new ChallengeService(
        provider: new ProofOfWorkChallengeProvider(ttlSeconds: 60),
        store: new RedisChallengeStore('default', 'antibot'),
        failureTracker: new ChallengeFailureTracker('default', 'antibot'),
        defaultDifficulty: $difficulty,
        adaptiveDifficulty: false,
        maxAttempts: $maxAttempts,
        failureTrackingTtlSeconds: 900,
        loggingEnabled: false,
    );
}

it('verifies a correctly solved challenge exactly once', function () {
    $service = makeChallengeService();
    $context = makeContext(['ip' => '203.0.113.50']);

    $challenge = $service->create($context);
    $answer = solveProofOfWork($challenge->nonce, $challenge->difficulty);

    expect($service->verify($challenge->id, $answer, $context))->toBeTrue()
        ->and($service->verify($challenge->id, $answer, $context))->toBeFalse(); // replay rejected
});

it('rejects a wrong answer', function () {
    $service = makeChallengeService();
    $context = makeContext(['ip' => '203.0.113.51']);

    $challenge = $service->create($context);

    expect($service->verify($challenge->id, '999999999', $context))->toBeFalse();
});

it('rejects an unknown challenge id', function () {
    $service = makeChallengeService();

    expect($service->verify('does-not-exist', '0', makeContext(['ip' => '203.0.113.52'])))->toBeFalse();
});

it('burns the challenge after too many wrong attempts', function () {
    $service = makeChallengeService(maxAttempts: 2);
    $context = makeContext(['ip' => '203.0.113.53']);

    $challenge = $service->create($context);
    $answer = solveProofOfWork($challenge->nonce, $challenge->difficulty);

    $service->verify($challenge->id, '0000000000', $context); // wrong, attempt 1
    $service->verify($challenge->id, '0000000000', $context); // wrong, attempt 2 -> burned

    // Even the *correct* answer no longer works once the challenge is burned.
    expect($service->verify($challenge->id, $answer, $context))->toBeFalse();
});
