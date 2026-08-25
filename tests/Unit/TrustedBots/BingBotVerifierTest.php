<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Enums\TrustedBotType;
use AlisherNPortfolio\LaravelAntiBot\Tests\Fakes\FakeDnsResolver;
use AlisherNPortfolio\LaravelAntiBot\TrustedBots\BingBotVerifier;

it('supports a user agent claiming Bingbot', function () {
    $verifier = new BingBotVerifier(new FakeDnsResolver);

    expect($verifier->supports(makeContext(['userAgent' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'])))->toBeTrue()
        ->and($verifier->supports(makeContext(['userAgent' => 'Mozilla/5.0 Chrome'])))->toBeFalse();
});

it('verifies a real Bingbot via matching reverse and forward DNS ending in search.msn.com', function () {
    $dns = (new FakeDnsResolver)
        ->willReverseTo('157.55.39.1', 'msnbot-157-55-39-1.search.msn.com')
        ->willResolveTo('msnbot-157-55-39-1.search.msn.com', ['157.55.39.1']);

    $result = (new BingBotVerifier($dns))->verify(makeContext(['ip' => '157.55.39.1', 'userAgent' => 'bingbot/2.0']));

    expect($result->verified)->toBeTrue()
        ->and($result->type)->toBe(TrustedBotType::BINGBOT);
});

it('rejects a fake Bingbot with a non-search.msn.com hostname', function () {
    $dns = (new FakeDnsResolver)->willReverseTo('1.2.3.4', 'crawler.evil-scraper.com');

    $result = (new BingBotVerifier($dns))->verify(makeContext(['ip' => '1.2.3.4', 'userAgent' => 'bingbot/2.0']));

    expect($result->verified)->toBeFalse()
        ->and($result->reason)->toBe('hostname_suffix_mismatch');
});

it('rejects when forward DNS does not confirm the original IP', function () {
    $dns = (new FakeDnsResolver)
        ->willReverseTo('157.55.39.1', 'msnbot-157-55-39-1.search.msn.com')
        ->willResolveTo('msnbot-157-55-39-1.search.msn.com', ['9.9.9.9']);

    $result = (new BingBotVerifier($dns))->verify(makeContext(['ip' => '157.55.39.1', 'userAgent' => 'bingbot']));

    expect($result->verified)->toBeFalse()
        ->and($result->reason)->toBe('forward_dns_mismatch');
});
