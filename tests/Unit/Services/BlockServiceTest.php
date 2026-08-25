<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Services\BlockService;
use AlisherNPortfolio\LaravelAntiBot\Stores\RedisBlockStore;

beforeEach(function () {
    $this->fakeRedis();
});

function makeBlockService(): BlockService
{
    return new BlockService(
        store: new RedisBlockStore('default', 'antibot'),
        enabled: true,
        durationsMinutes: ['first' => 5, 'second' => 30, 'third' => 60, 'repeat' => 360],
        violationTtlSeconds: 86400,
    );
}

it('is not blocked before any violation', function () {
    expect(makeBlockService()->isBlocked(makeContext(['ip' => '203.0.113.40'])))->toBeFalse();
});

it('is blocked immediately after block() is called', function () {
    $service = makeBlockService();
    $context = makeContext(['ip' => '203.0.113.41']);

    $service->block($context);

    expect($service->isBlocked($context))->toBeTrue();
});

it('does not block a different client', function () {
    $service = makeBlockService();

    $service->block(makeContext(['ip' => '203.0.113.42']));

    expect($service->isBlocked(makeContext(['ip' => '203.0.113.43'])))->toBeFalse();
});

it('does nothing when disabled', function () {
    $service = new BlockService(
        store: new RedisBlockStore('default', 'antibot'),
        enabled: false,
        durationsMinutes: ['first' => 5, 'second' => 30, 'third' => 60, 'repeat' => 360],
        violationTtlSeconds: 86400,
    );

    $context = makeContext(['ip' => '203.0.113.44']);
    $service->block($context);

    expect($service->isBlocked($context))->toBeFalse();
});

it('escalates the block duration on repeated violations', function () {
    $redis = $this->fakeRedis();
    $service = makeBlockService();
    $context = makeContext(['ip' => '203.0.113.45']);

    $service->block($context); // 1st: 5 min
    $service->block($context); // 2nd: 30 min
    $service->block($context); // 3rd: 60 min
    $service->block($context); // 4th+: 360 min (repeat)

    // The store namespaces the block key as "antibot:block:{hash}".
    $hash = AlisherNPortfolio\LaravelAntiBot\Support\Hashing::shortHash('203.0.113.45');
    $ttl = $redis->ttl("antibot:block:{$hash}");

    expect($ttl)->toBeGreaterThan(300 * 60); // repeat duration (360 min) applied last
});
