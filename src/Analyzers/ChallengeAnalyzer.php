<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Analyzers;

use AlisherNPortfolio\LaravelAntiBot\Contracts\Analyzer;
use AlisherNPortfolio\LaravelAntiBot\DTO\AnalyzerResult;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\Services\ChallengeFailureTracker;
use AlisherNPortfolio\LaravelAntiBot\Services\VerificationService;
use AlisherNPortfolio\LaravelAntiBot\Support\Hashing;

/**
 * Turns a client's challenge history into a risk signal: repeated recent
 * failures raise the score (escalating per additional failure), while a
 * currently-valid verification cookie lowers it. ChallengeService is
 * responsible for recording failures/successes into the tracker — this
 * analyzer only reads that history.
 */
final class ChallengeAnalyzer implements Analyzer
{
    public function __construct(
        private readonly ChallengeFailureTracker $tracker,
        private readonly VerificationService $verification,
        private readonly int $failure1Score,
        private readonly int $failure2Score,
        private readonly int $failure3PlusScore,
        private readonly int $successfulVerificationScore,
    ) {}

    public function analyze(AntiBotContext $context): AnalyzerResult
    {
        $clientKey = Hashing::shortHash($context->ip);
        $failures = $this->tracker->failureCount($clientKey);

        $score = 0;
        $reason = null;

        $score += match (true) {
            $failures >= 3 => $this->failure3PlusScore,
            $failures === 2 => $this->failure2Score,
            $failures === 1 => $this->failure1Score,
            default => 0,
        };

        if ($failures > 0) {
            $reason = 'repeated_challenge_failures';
        }

        if ($this->verification->isValid($context)) {
            $score += $this->successfulVerificationScore;
            $reason = $reason === null ? 'verified' : $reason;
        }

        if ($score === 0 && $reason === null) {
            return AnalyzerResult::none('challenge');
        }

        return new AnalyzerResult('challenge', $score, $reason, ['failures' => $failures]);
    }
}
