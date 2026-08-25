<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\DTO;

use AlisherNPortfolio\LaravelAntiBot\Enums\TrustedBotType;

final readonly class TrustedBotResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $verified,
        public ?TrustedBotType $type = null,
        public ?string $reason = null,
        public array $metadata = [],
    ) {}

    public static function notVerified(?string $reason = null): self
    {
        return new self(verified: false, reason: $reason);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function verified(TrustedBotType $type, ?string $reason = null, array $metadata = []): self
    {
        return new self(verified: true, type: $type, reason: $reason, metadata: $metadata);
    }
}
