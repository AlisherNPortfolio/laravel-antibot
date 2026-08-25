<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Contracts;

use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\DTO\TrustedBotResult;
use AlisherNPortfolio\LaravelAntiBot\Enums\TrustedBotType;

/**
 * A network-verified trusted crawler check. `supports()` may look at the
 * claimed User-Agent to decide whether it's worth attempting verification,
 * but `verify()` must never trust the User-Agent alone — it must confirm
 * the request actually originates from the crawler's network (e.g. via
 * reverse+forward DNS confirmation).
 */
interface TrustedBotVerifier
{
    public function type(): TrustedBotType;

    public function supports(AntiBotContext $context): bool;

    public function verify(AntiBotContext $context): TrustedBotResult;
}
