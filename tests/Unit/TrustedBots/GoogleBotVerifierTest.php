<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Enums\TrustedBotType;
use AlisherNPortfolio\LaravelAntiBot\Tests\Fakes\FakeDnsResolver;
use AlisherNPortfolio\LaravelAntiBot\TrustedBots\GoogleBotVerifier;

it('supports a user agent claiming Googlebot', function () {
    $verifier = new GoogleBotVerifier(new FakeDnsResolver);

    expect($verifier->supports(makeContext(['ip' => '66.249.66.1', 'userAgent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])))->toBeTrue()
        ->and($verifier->supports(makeContext(['ip' => '1.2.3.4', 'userAgent' => 'Mozilla/5.0 Chrome'])))->toBeFalse();
});

it('verifies a real Googlebot via matching reverse and forward DNS', function () {
    $dns = (new FakeDnsResolver)
        ->willReverseTo('66.249.66.1', 'crawl-66-249-66-1.googlebot.com')
        ->willResolveTo('crawl-66-249-66-1.googlebot.com', ['66.249.66.1']);

    $verifier = new GoogleBotVerifier($dns);
    $result = $verifier->verify(makeContext(['ip' => '66.249.66.1', 'userAgent' => 'Googlebot/2.1']));

    expect($result->verified)->toBeTrue()
        ->and($result->type)->toBe(TrustedBotType::GOOGLEBOT);
});

it('accepts google.com and googleusercontent.com as valid suffixes too', function () {
    $dns = (new FakeDnsResolver)
        ->willReverseTo('1.1.1.1', 'rate-limited-proxy-1-1-1-1.google.com')
        ->willResolveTo('rate-limited-proxy-1-1-1-1.google.com', ['1.1.1.1']);

    expect((new GoogleBotVerifier($dns))->verify(makeContext(['ip' => '1.1.1.1', 'userAgent' => 'Googlebot']))->verified)->toBeTrue();

    $dns2 = (new FakeDnsResolver)
        ->willReverseTo('2.2.2.2', '123-45-67-89.gae.googleusercontent.com')
        ->willResolveTo('123-45-67-89.gae.googleusercontent.com', ['2.2.2.2']);

    expect((new GoogleBotVerifier($dns2))->verify(makeContext(['ip' => '2.2.2.2', 'userAgent' => 'Googlebot']))->verified)->toBeTrue();
});

it('rejects a fake Googlebot whose reverse DNS does not belong to a Google domain', function () {
    $dns = (new FakeDnsResolver)->willReverseTo('9.9.9.9', 'crawler.evil-scraper.com');

    $result = (new GoogleBotVerifier($dns))->verify(makeContext(['ip' => '9.9.9.9', 'userAgent' => 'Googlebot/2.1']));

    expect($result->verified)->toBeFalse()
        ->and($result->reason)->toBe('hostname_suffix_mismatch');
});

it('rejects a lookalike domain that merely ends with the suffix substring', function () {
    // "evilgooglebot.com" ends with the substring "googlebot.com" but is not
    // a subdomain of it — a naive str_ends_with() check would wrongly accept this.
    $dns = (new FakeDnsResolver)->willReverseTo('9.9.9.9', 'www.evilgooglebot.com');

    $result = (new GoogleBotVerifier($dns))->verify(makeContext(['ip' => '9.9.9.9', 'userAgent' => 'Googlebot/2.1']));

    expect($result->verified)->toBeFalse();
});

it('rejects when the forward DNS lookup does not resolve back to the original IP', function () {
    $dns = (new FakeDnsResolver)
        ->willReverseTo('66.249.66.1', 'crawl-66-249-66-1.googlebot.com')
        ->willResolveTo('crawl-66-249-66-1.googlebot.com', ['1.2.3.4']); // different IP

    $result = (new GoogleBotVerifier($dns))->verify(makeContext(['ip' => '66.249.66.1', 'userAgent' => 'Googlebot']));

    expect($result->verified)->toBeFalse()
        ->and($result->reason)->toBe('forward_dns_mismatch');
});

it('rejects when reverse DNS fails entirely (no PTR record)', function () {
    $result = (new GoogleBotVerifier(new FakeDnsResolver))->verify(makeContext(['ip' => '66.249.66.1', 'userAgent' => 'Googlebot']));

    expect($result->verified)->toBeFalse()
        ->and($result->reason)->toBe('reverse_dns_failed');
});
