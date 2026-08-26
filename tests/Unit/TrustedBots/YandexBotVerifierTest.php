<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Enums\TrustedBotType;
use AlisherNPortfolio\LaravelAntiBot\Tests\Fakes\FakeDnsResolver;
use AlisherNPortfolio\LaravelAntiBot\TrustedBots\YandexBotVerifier;

it('supports a user agent claiming YandexBot', function () {
    $verifier = new YandexBotVerifier(new FakeDnsResolver);

    expect($verifier->supports(makeContext(['userAgent' => 'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)'])))->toBeTrue()
        ->and($verifier->supports(makeContext(['userAgent' => 'Mozilla/5.0 Chrome'])))->toBeFalse();
});

it('verifies a real YandexBot via matching reverse and forward DNS ending in yandex.ru', function () {
    $dns = (new FakeDnsResolver)
        ->willReverseTo('5.255.253.1', 'spider-nossl-5-255-253-1.yandex.ru')
        ->willResolveTo('spider-nossl-5-255-253-1.yandex.ru', ['5.255.253.1']);

    $result = (new YandexBotVerifier($dns))->verify(makeContext(['ip' => '5.255.253.1', 'userAgent' => 'YandexBot/3.0']));

    expect($result->verified)->toBeTrue()
        ->and($result->type)->toBe(TrustedBotType::YANDEXBOT);
});

it('rejects a fake YandexBot with a non-yandex hostname', function () {
    $dns = (new FakeDnsResolver)->willReverseTo('1.2.3.4', 'crawler.evil-scraper.com');

    $result = (new YandexBotVerifier($dns))->verify(makeContext(['ip' => '1.2.3.4', 'userAgent' => 'YandexBot/3.0']));

    expect($result->verified)->toBeFalse()
        ->and($result->reason)->toBe('hostname_suffix_mismatch');
});

it('rejects when forward DNS does not confirm the original IP', function () {
    $dns = (new FakeDnsResolver)
        ->willReverseTo('5.255.253.1', 'spider-nossl-5-255-253-1.yandex.ru')
        ->willResolveTo('spider-nossl-5-255-253-1.yandex.ru', ['9.9.9.9']);

    $result = (new YandexBotVerifier($dns))->verify(makeContext(['ip' => '5.255.253.1', 'userAgent' => 'YandexBot/3.0']));

    expect($result->verified)->toBeFalse()
        ->and($result->reason)->toBe('forward_dns_mismatch');
});
