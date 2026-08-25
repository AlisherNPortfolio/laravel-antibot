<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\DTO;

/**
 * The signal an {@see \AlisherNPortfolio\LaravelAntiBot\Contracts\Analyzer}
 * contributes to the risk score. Analyzers never decide ALLOW/CHALLENGE/BLOCK
 * themselves — that decision belongs solely to RiskScoreEngine.
 */
final readonly class AnalyzerResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $analyzer,
        public int $score,
        public ?string $reason = null,
        public array $metadata = [],
    ) {}

    public static function none(string $analyzer): self
    {
        return new self($analyzer, 0);
    }
}
