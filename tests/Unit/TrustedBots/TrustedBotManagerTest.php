<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Contracts\DnsResolver;
use AlisherNPortfolio\LaravelAntiBot\Enums\TrustedBotType;
use AlisherNPortfolio\LaravelAntiBot\Tests\Fakes\FakeDnsResolver;
use AlisherNPortfolio\LaravelAntiBot\TrustedBots\GoogleBotVerifier;
use AlisherNPortfolio\LaravelAntiBot\TrustedBots\TrustedBotManager;

beforeEach(function () {
    $this->fakeRedis();
});

/**
 * Wraps a DnsResolver and counts how many times reverse() was called, so
 * tests can prove the manager's cache prevents repeated DNS lookups.
 */
function countingDnsResolver(DnsResolver $inner, int &$calls): DnsResolver
{
    return new class($inner, $calls) implements DnsResolver
    {
        public function __construct(private DnsResolver $inner, private int &$calls) {}

        public function reverse(string $ip): ?string
        {
            $this->calls++;

            return $this->inner->reverse($ip);
        }

        public function resolve(string $hostname): array
        {
            return $this->inner->resolve($hostname);
        }
    };
}

it('returns not-verified when nothing claims to be a trusted bot', function () {
    $manager = new TrustedBotManager(
        verifiers: [new GoogleBotVerifier(new FakeDnsResolver)],
        enabled: true,
        cacheEnabled: true,
        cacheTtlSeconds: 3600,
        negativeCacheTtlSeconds: 30,
        redisConnection: 'default',
        keyPrefix: 'antibot',
    );

    $result = $manager->check(makeContext(['userAgent' => 'Mozilla/5.0 Chrome']));

    expect($result->verified)->toBeFalse()
        ->and($result->reason)->toBe('no_trusted_bot_claim');
});

it('returns not-verified immediately when disabled, without touching DNS', function () {
    $calls = 0;
    $dns = countingDnsResolver(new FakeDnsResolver, $calls);

    $manager = new TrustedBotManager(
        verifiers: [new GoogleBotVerifier($dns)],
        enabled: false,
        cacheEnabled: true,
        cacheTtlSeconds: 3600,
        negativeCacheTtlSeconds: 30,
        redisConnection: 'default',
        keyPrefix: 'antibot',
    );

    $result = $manager->check(makeContext(['ip' => '66.249.66.1', 'userAgent' => 'Googlebot']));

    expect($result->verified)->toBeFalse()
        ->and($result->reason)->toBe('trusted_bots_disabled')
        ->and($calls)->toBe(0);
});

it('caches a successful verification so a second request skips DNS', function () {
    $calls = 0;
    $inner = (new FakeDnsResolver)
        ->willReverseTo('66.249.66.1', 'crawl-66-249-66-1.googlebot.com')
        ->willResolveTo('crawl-66-249-66-1.googlebot.com', ['66.249.66.1']);
    $dns = countingDnsResolver($inner, $calls);

    $manager = new TrustedBotManager(
        verifiers: [new GoogleBotVerifier($dns)],
        enabled: true,
        cacheEnabled: true,
        cacheTtlSeconds: 3600,
        negativeCacheTtlSeconds: 30,
        redisConnection: 'default',
        keyPrefix: 'antibot',
    );

    $context = makeContext(['ip' => '66.249.66.1', 'userAgent' => 'Googlebot']);

    $first = $manager->check($context);
    $second = $manager->check($context);

    expect($first->verified)->toBeTrue()
        ->and($first->type)->toBe(TrustedBotType::GOOGLEBOT)
        ->and($second->verified)->toBeTrue()
        ->and($second->type)->toBe(TrustedBotType::GOOGLEBOT)
        ->and($calls)->toBe(1);
});

it('caches a failed verification briefly to blunt DNS-lookup abuse', function () {
    $calls = 0;
    $dns = countingDnsResolver(new FakeDnsResolver, $calls); // never configured -> always fails

    $manager = new TrustedBotManager(
        verifiers: [new GoogleBotVerifier($dns)],
        enabled: true,
        cacheEnabled: true,
        cacheTtlSeconds: 3600,
        negativeCacheTtlSeconds: 30,
        redisConnection: 'default',
        keyPrefix: 'antibot',
    );

    $context = makeContext(['ip' => '66.249.66.1', 'userAgent' => 'Googlebot']);

    $first = $manager->check($context);
    $second = $manager->check($context);

    expect($first->verified)->toBeFalse()
        ->and($second->verified)->toBeFalse()
        ->and($calls)->toBe(1);
});

it('degrades to a fresh DNS check (never a hard failure) when the cache is unavailable', function () {
    // Bind a redis manager whose connection() throws, simulating a Redis outage
    // that should only ever cost a cache hit, never break trusted-bot verification.
    $this->app->instance('redis', new class
    {
        public function connection($name = null)
        {
            throw new RuntimeException('redis down');
        }
    });

    $dns = (new FakeDnsResolver)
        ->willReverseTo('66.249.66.1', 'crawl-66-249-66-1.googlebot.com')
        ->willResolveTo('crawl-66-249-66-1.googlebot.com', ['66.249.66.1']);

    $manager = new TrustedBotManager(
        verifiers: [new GoogleBotVerifier($dns)],
        enabled: true,
        cacheEnabled: true,
        cacheTtlSeconds: 3600,
        negativeCacheTtlSeconds: 30,
        redisConnection: 'default',
        keyPrefix: 'antibot',
    );

    $result = $manager->check(makeContext(['ip' => '66.249.66.1', 'userAgent' => 'Googlebot']));

    expect($result->verified)->toBeTrue();
});
