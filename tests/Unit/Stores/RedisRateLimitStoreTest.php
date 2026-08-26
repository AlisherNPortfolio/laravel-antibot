<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Stores\RedisRateLimitStore;

beforeEach(function () {
    $this->fakeRedis();
});

it('returns the running hit count within a window', function () {
    $store = new RedisRateLimitStore('default', 'antibot');

    expect($store->hit('client-a', 10))->toBe(1)
        ->and($store->hit('client-a', 10))->toBe(2)
        ->and($store->hit('client-a', 10))->toBe(3);
});

it('keeps independent counters per key', function () {
    $store = new RedisRateLimitStore('default', 'antibot');

    $store->hit('client-b', 10);
    $store->hit('client-b', 10);

    expect($store->hit('client-c', 10))->toBe(1);
});

/**
 * Regression test: the sliding-window cutoff passed to ZREMRANGEBYSCORE
 * must be a string. `ArrayRedisConnection::zremrangebyscore()` types its
 * range arguments as `string` (matching the real phpredis extension's
 * arginfo, which requires `string` to support "-inf"/"+inf"/"(exclusive"
 * range syntax) — under this package's `declare(strict_types=1)`, passing
 * a raw float (e.g. an unwrapped `microtime(true) - $windowSeconds`) would
 * throw a TypeError against real Redis and fail this call here too.
 */
it('never throws when computing the sliding-window cutoff', function () {
    $store = new RedisRateLimitStore('default', 'antibot');

    expect(fn () => $store->hit('client-d', 10))->not->toThrow(TypeError::class);
});
