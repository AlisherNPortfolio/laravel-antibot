<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature');

/**
 * Shared helper for building an AntiBotContext with sensible defaults,
 * overriding only what a given test cares about.
 */
function makeContext(array $overrides = []): AntiBotContext
{
    $defaults = [
        'ip' => '203.0.113.10',
        'userAgent' => 'Mozilla/5.0 Test Browser',
        'sessionId' => null,
        'verificationToken' => null,
        'method' => 'GET',
        'path' => '/',
        'routeName' => '',
        'referer' => null,
        'hasCookies' => false,
        'hasJavascriptVerification' => false,
        'expectsJson' => false,
    ];

    $attributes = array_merge($defaults, $overrides);

    return new AntiBotContext(...$attributes);
}

/**
 * Black-box PoW solver mirroring resources/js/antibot/challenge.js and
 * ProofOfWorkChallengeProvider, used to produce a genuinely correct
 * answer for deterministic tests.
 */
function solveProofOfWork(string $nonce, int $difficulty): string
{
    $requiredNibbles = (int) ceil($difficulty / 4);
    $counter = 0;

    while (true) {
        $hash = hash('sha256', $nonce.$counter);
        $binary = '';

        foreach (str_split(substr($hash, 0, $requiredNibbles)) as $hexDigit) {
            $binary .= str_pad(base_convert($hexDigit, 16, 2), 4, '0', STR_PAD_LEFT);
        }

        if (substr($binary, 0, $difficulty) === str_repeat('0', $difficulty)) {
            return (string) $counter;
        }

        $counter++;
    }
}
