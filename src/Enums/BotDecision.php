<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Enums;

enum BotDecision: string
{
    case ALLOW = 'allow';
    case CHALLENGE = 'challenge';
    case BLOCK = 'block';
}
