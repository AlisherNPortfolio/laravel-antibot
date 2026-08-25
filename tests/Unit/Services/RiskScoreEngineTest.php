<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Contracts\Analyzer;
use AlisherNPortfolio\LaravelAntiBot\DTO\AnalyzerResult;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\Enums\BotDecision;
use AlisherNPortfolio\LaravelAntiBot\Services\RiskScoreEngine;

function stubAnalyzer(string $name, int $score, ?string $reason = null): Analyzer
{
    return new class($name, $score, $reason) implements Analyzer
    {
        public function __construct(private string $name, private int $score, private ?string $reason) {}

        public function analyze(AntiBotContext $context): AnalyzerResult
        {
            return new AnalyzerResult($this->name, $this->score, $this->reason);
        }
    };
}

it('allows when the summed score is at or below the allow threshold', function () {
    $engine = new RiskScoreEngine([stubAnalyzer('a', 10), stubAnalyzer('b', 20)], allowMaxScore: 30, challengeMaxScore: 70);

    $result = $engine->evaluate(makeContext());

    expect($result->decision)->toBe(BotDecision::ALLOW)
        ->and($result->score)->toBe(30);
});

it('challenges when the score is between the two thresholds', function () {
    $engine = new RiskScoreEngine([stubAnalyzer('a', 50)], allowMaxScore: 30, challengeMaxScore: 70);

    $result = $engine->evaluate(makeContext());

    expect($result->decision)->toBe(BotDecision::CHALLENGE);
});

it('blocks when the score exceeds the challenge threshold', function () {
    $engine = new RiskScoreEngine([stubAnalyzer('a', 90)], allowMaxScore: 30, challengeMaxScore: 70);

    $result = $engine->evaluate(makeContext());

    expect($result->decision)->toBe(BotDecision::BLOCK);
});

it('clamps the total score to 100 even if analyzers sum higher', function () {
    $engine = new RiskScoreEngine([stubAnalyzer('a', 80), stubAnalyzer('b', 80)], allowMaxScore: 30, challengeMaxScore: 70);

    $result = $engine->evaluate(makeContext());

    expect($result->score)->toBe(100);
});

it('applies a negative-scoring analyzer to reduce the total', function () {
    $engine = new RiskScoreEngine([stubAnalyzer('a', 50), stubAnalyzer('b', -30)], allowMaxScore: 30, challengeMaxScore: 70);

    $result = $engine->evaluate(makeContext());

    expect($result->score)->toBe(20)
        ->and($result->decision)->toBe(BotDecision::ALLOW);
});
