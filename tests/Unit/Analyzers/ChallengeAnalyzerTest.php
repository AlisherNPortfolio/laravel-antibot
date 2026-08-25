<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Analyzers\ChallengeAnalyzer;
use AlisherNPortfolio\LaravelAntiBot\Services\ChallengeFailureTracker;
use AlisherNPortfolio\LaravelAntiBot\Services\VerificationService;
use AlisherNPortfolio\LaravelAntiBot\Support\Hashing;

beforeEach(function () {
    $this->fakeRedis();
});

function makeChallengeAnalyzer(ChallengeFailureTracker $tracker, VerificationService $verification): ChallengeAnalyzer
{
    return new ChallengeAnalyzer(
        tracker: $tracker,
        verification: $verification,
        failure1Score: 20,
        failure2Score: 30,
        failure3PlusScore: 50,
        successfulVerificationScore: -30,
    );
}

it('scores nothing for a client with no history', function () {
    $tracker = new ChallengeFailureTracker('default', 'antibot');
    $verification = new VerificationService('antibot_verified', 30, true, 'lax');

    $result = makeChallengeAnalyzer($tracker, $verification)->analyze(makeContext(['ip' => '203.0.113.30']));

    expect($result->score)->toBe(0);
});

it('escalates the score with each additional recent failure', function () {
    $tracker = new ChallengeFailureTracker('default', 'antibot');
    $verification = new VerificationService('antibot_verified', 30, true, 'lax');
    $analyzer = makeChallengeAnalyzer($tracker, $verification);
    $key = Hashing::shortHash('203.0.113.31');

    $tracker->recordFailure($key, 900);
    $afterOne = $analyzer->analyze(makeContext(['ip' => '203.0.113.31']));

    $tracker->recordFailure($key, 900);
    $afterTwo = $analyzer->analyze(makeContext(['ip' => '203.0.113.31']));

    $tracker->recordFailure($key, 900);
    $afterThree = $analyzer->analyze(makeContext(['ip' => '203.0.113.31']));

    expect($afterOne->score)->toBe(20)
        ->and($afterTwo->score)->toBe(30)
        ->and($afterThree->score)->toBe(50);
});

it('reduces risk for a client holding a currently-valid verification cookie', function () {
    $tracker = new ChallengeFailureTracker('default', 'antibot');
    $verification = new VerificationService('antibot_verified', 30, true, 'lax');
    $token = $verification->issueToken();

    $result = makeChallengeAnalyzer($tracker, $verification)->analyze(makeContext([
        'ip' => '203.0.113.32',
        'verificationToken' => $token,
    ]));

    expect($result->score)->toBe(-30)
        ->and($result->reason)->toBe('verified');
});

it('ignores an expired or tampered verification token', function () {
    $tracker = new ChallengeFailureTracker('default', 'antibot');
    $verification = new VerificationService('antibot_verified', 30, true, 'lax');

    $result = makeChallengeAnalyzer($tracker, $verification)->analyze(makeContext([
        'ip' => '203.0.113.33',
        'verificationToken' => 'not-a-real-token',
    ]));

    expect($result->score)->toBe(0);
});
