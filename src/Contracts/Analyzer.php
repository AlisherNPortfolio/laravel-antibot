<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Contracts;

use AlisherNPortfolio\LaravelAntiBot\DTO\AnalyzerResult;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;

/**
 * An analyzer only ever contributes a signal (a score + reason). It must
 * never decide ALLOW/CHALLENGE/BLOCK — that decision belongs solely to
 * the RiskScoreEngine, so analyzers can be composed freely without
 * fighting over the final outcome.
 */
interface Analyzer
{
    public function analyze(AntiBotContext $context): AnalyzerResult;
}
