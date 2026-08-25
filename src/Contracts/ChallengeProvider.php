<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Contracts;

use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\DTO\Challenge;

/**
 * A pluggable challenge mechanism. The bundled implementation is a
 * browser proof-of-work puzzle, but this contract allows host
 * applications to swap in Turnstile, hCaptcha, reCAPTCHA or a fully
 * custom challenge without touching the rest of the package.
 */
interface ChallengeProvider
{
    public function create(AntiBotContext $context, int $difficulty): Challenge;

    public function verify(Challenge $challenge, string $answer): bool;
}
