<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\ValueObjects;

/**
 * Immutable 0-100 score accumulator. Every mutation returns a new instance
 * and is clamped to the valid range, so callers can never push the score
 * out of bounds regardless of how many analyzers contribute.
 */
final class RiskScore
{
    public const MIN = 0;

    public const MAX = 100;

    private function __construct(private readonly int $value) {}

    public static function zero(): self
    {
        return new self(self::MIN);
    }

    public static function of(int $value): self
    {
        return new self(self::clamp($value));
    }

    public function add(int $points): self
    {
        return new self(self::clamp($this->value + $points));
    }

    public function subtract(int $points): self
    {
        return new self(self::clamp($this->value - $points));
    }

    public function value(): int
    {
        return $this->value;
    }

    private static function clamp(int $value): int
    {
        return max(self::MIN, min(self::MAX, $value));
    }
}
