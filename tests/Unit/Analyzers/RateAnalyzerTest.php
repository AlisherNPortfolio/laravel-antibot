<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Analyzers\RateAnalyzer;
use AlisherNPortfolio\LaravelAntiBot\Stores\RedisRateLimitStore;

beforeEach(function () {
    $this->fakeRedis();
});

it('does not score a client under the limit', function () {
    $limiter = new RedisRateLimitStore('default', 'antibot');
    $analyzer = new RateAnalyzer($limiter, [
        '10_seconds' => ['seconds' => 10, 'limit' => 5, 'score' => 40],
    ]);

    $context = makeContext(['ip' => '198.51.100.1']);

    for ($i = 0; $i < 5; $i++) {
        $result = $analyzer->analyze($context);
    }

    expect($result->score)->toBe(0);
});

it('scores a client that exceeds a window limit', function () {
    $limiter = new RedisRateLimitStore('default', 'antibot');
    $analyzer = new RateAnalyzer($limiter, [
        '10_seconds' => ['seconds' => 10, 'limit' => 3, 'score' => 40],
    ]);

    $context = makeContext(['ip' => '198.51.100.2']);

    for ($i = 0; $i < 3; $i++) {
        $analyzer->analyze($context);
    }

    $result = $analyzer->analyze($context); // 4th hit exceeds limit of 3

    expect($result->score)->toBe(40)
        ->and($result->reason)->toBe('rate_limit_exceeded');
});

it('sums scores across multiple independently-exceeded windows', function () {
    $limiter = new RedisRateLimitStore('default', 'antibot');
    $analyzer = new RateAnalyzer($limiter, [
        '10_seconds' => ['seconds' => 10, 'limit' => 1, 'score' => 40],
        '1_minute' => ['seconds' => 60, 'limit' => 1, 'score' => 30],
    ]);

    $context = makeContext(['ip' => '198.51.100.3']);

    $analyzer->analyze($context); // 1st hit: within both limits
    $result = $analyzer->analyze($context); // 2nd hit: exceeds both

    expect($result->score)->toBe(70);
});

it('keeps separate counters for different clients', function () {
    $limiter = new RedisRateLimitStore('default', 'antibot');
    $analyzer = new RateAnalyzer($limiter, [
        '10_seconds' => ['seconds' => 10, 'limit' => 1, 'score' => 40],
    ]);

    $analyzer->analyze(makeContext(['ip' => '198.51.100.4']));
    $analyzer->analyze(makeContext(['ip' => '198.51.100.4']));

    $resultForOtherClient = $analyzer->analyze(makeContext(['ip' => '198.51.100.5']));

    expect($resultForOtherClient->score)->toBe(0);
});
