<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Analyzers;

use AlisherNPortfolio\LaravelAntiBot\Contracts\Analyzer;
use AlisherNPortfolio\LaravelAntiBot\Contracts\RateLimiter;
use AlisherNPortfolio\LaravelAntiBot\DTO\AnalyzerResult;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\Support\Hashing;

/**
 * Scores request volume against multiple sliding windows (e.g. 10s/1m/5m).
 * Each configured window carries its own limit and score contribution, so
 * a short aggressive burst and a sustained elevated rate can be weighted
 * independently.
 */
final class RateAnalyzer implements Analyzer
{
    /**
     * @param  array<string, array{seconds: int, limit: int, score: int}>  $windows
     */
    public function __construct(
        private readonly RateLimiter $rateLimiter,
        private readonly array $windows,
    ) {}

    public function analyze(AntiBotContext $context): AnalyzerResult
    {
        $identity = Hashing::shortHash($context->ip);

        $score = 0;
        $triggeredWindows = [];

        foreach ($this->windows as $name => $window) {
            $hits = $this->rateLimiter->hit($identity, $window['seconds']);

            if ($hits > $window['limit']) {
                $score += $window['score'];
                $triggeredWindows[$name] = $hits;
            }
        }

        if ($triggeredWindows === []) {
            return AnalyzerResult::none('rate');
        }

        return new AnalyzerResult('rate', $score, 'rate_limit_exceeded', [
            'windows' => $triggeredWindows,
        ]);
    }
}
