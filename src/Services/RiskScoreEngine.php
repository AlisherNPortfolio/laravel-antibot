<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Services;

use AlisherNPortfolio\LaravelAntiBot\Contracts\Analyzer;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\DTO\BotDecisionResult;
use AlisherNPortfolio\LaravelAntiBot\Enums\BotDecision;
use AlisherNPortfolio\LaravelAntiBot\ValueObjects\RiskScore;

/**
 * Runs every configured analyzer, sums their independent signals into a
 * single 0-100 RiskScore, and maps that score onto a final decision.
 * Analyzers never decide the outcome themselves — this is the only place
 * ALLOW/CHALLENGE/BLOCK is chosen.
 *
 * The detailed per-analyzer reasons collected here are for internal
 * logging only; callers must not forward them to untrusted clients.
 */
final class RiskScoreEngine
{
    /**
     * @param  list<Analyzer>  $analyzers
     */
    public function __construct(
        private readonly array $analyzers,
        private readonly int $allowMaxScore,
        private readonly int $challengeMaxScore,
    ) {}

    public function evaluate(AntiBotContext $context): BotDecisionResult
    {
        $score = RiskScore::zero();
        $reasons = [];
        $analyzerMetadata = [];

        foreach ($this->analyzers as $analyzer) {
            $result = $analyzer->analyze($context);

            if ($result->score !== 0) {
                $score = $score->add($result->score);
            }

            if ($result->reason !== null) {
                $reasons[] = $result->reason;
                $analyzerMetadata[$result->analyzer] = [
                    'score' => $result->score,
                    'reason' => $result->reason,
                    'metadata' => $result->metadata,
                ];
            }
        }

        $decision = match (true) {
            $score->value() > $this->challengeMaxScore => BotDecision::BLOCK,
            $score->value() > $this->allowMaxScore => BotDecision::CHALLENGE,
            default => BotDecision::ALLOW,
        };

        return new BotDecisionResult(
            decision: $decision,
            score: $score->value(),
            reason: $reasons === [] ? null : implode(',', $reasons),
            metadata: $analyzerMetadata,
        );
    }
}
