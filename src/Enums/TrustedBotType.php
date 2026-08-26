<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Enums;

/**
 * Extend this enum (via a PR) to add new verified crawler types.
 * A verifier's supports()/verify() pairing is what actually grants trust —
 * this enum only labels an already-verified result.
 */
enum TrustedBotType: string
{
    case GOOGLEBOT = 'googlebot';
    case BINGBOT = 'bingbot';
    case YANDEXBOT = 'yandexbot';
}
