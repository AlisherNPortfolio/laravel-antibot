<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\DTO;

use AlisherNPortfolio\LaravelAntiBot\Enums\BotDecision;

final readonly class BotDecisionResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public BotDecision $decision,
        public int $score,
        public ?string $reason = null,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function allow(int $score = 0, ?string $reason = null, array $metadata = []): self
    {
        return new self(BotDecision::ALLOW, $score, $reason, $metadata);
    }
}
