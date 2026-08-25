<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Contracts;

use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\DTO\BotDecisionResult;
use AlisherNPortfolio\LaravelAntiBot\DTO\Challenge;

/**
 * The package's primary public entry point. Host applications and the
 * bundled middleware/controller should depend on this contract rather
 * than the concrete AntiBotManager.
 */
interface AntiBotService
{
    public function inspect(AntiBotContext $context): BotDecisionResult;

    /**
     * $riskScore, when supplied, is the score already computed by a prior
     * inspect() call for this same request — passing it lets adaptive
     * difficulty scale without re-running (and double-counting) analyzers.
     */
    public function createChallenge(AntiBotContext $context, ?int $riskScore = null): Challenge;

    public function verifyChallenge(string $challengeId, string $answer, AntiBotContext $context): bool;
}
