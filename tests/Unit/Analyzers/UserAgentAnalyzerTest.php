<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Analyzers\UserAgentAnalyzer;

function makeUserAgentAnalyzer(): UserAgentAnalyzer
{
    return new UserAgentAnalyzer(
        suspiciousPatterns: ['curl', 'wget', 'python-requests', 'scrapy'],
        suspiciousScore: 20,
        missingUserAgentScore: 15,
        spoofedTrustedBotScore: 40,
    );
}

it('scores nothing for an ordinary browser', function () {
    $result = makeUserAgentAnalyzer()->analyze(makeContext(['userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120']));

    expect($result->score)->toBe(0);
});

it('scores a missing user agent', function () {
    $result = makeUserAgentAnalyzer()->analyze(makeContext(['userAgent' => '']));

    expect($result->score)->toBe(15)
        ->and($result->reason)->toBe('missing_user_agent');
});

it('scores a known scripted HTTP client', function () {
    $result = makeUserAgentAnalyzer()->analyze(makeContext(['userAgent' => 'python-requests/2.31']));

    expect($result->score)->toBe(20)
        ->and($result->reason)->toBe('suspicious_user_agent');
});

it('never hard-blocks by itself (score stays a mild signal)', function () {
    $result = makeUserAgentAnalyzer()->analyze(makeContext(['userAgent' => 'curl/8.0']));

    expect($result->score)->toBeLessThan(100);
});

it('scores an unverified Googlebot/Bingbot claim higher, as a likely spoof', function () {
    $result = makeUserAgentAnalyzer()->analyze(makeContext(['userAgent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)']));

    expect($result->score)->toBe(40)
        ->and($result->reason)->toBe('unverified_trusted_bot_claim');
});

it('scores an unverified YandexBot claim the same way, as a likely spoof', function () {
    $result = makeUserAgentAnalyzer()->analyze(makeContext(['userAgent' => 'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)']));

    expect($result->score)->toBe(40)
        ->and($result->reason)->toBe('unverified_trusted_bot_claim');
});

it('honours a custom trusted-bot-claim pattern list', function () {
    $analyzer = new UserAgentAnalyzer(
        suspiciousPatterns: [],
        suspiciousScore: 20,
        missingUserAgentScore: 15,
        spoofedTrustedBotScore: 40,
        trustedBotClaimPatterns: ['duckduckbot'],
    );

    $result = $analyzer->analyze(makeContext(['userAgent' => 'DuckDuckBot/1.1']));

    expect($result->score)->toBe(40)
        ->and($result->reason)->toBe('unverified_trusted_bot_claim');
});
