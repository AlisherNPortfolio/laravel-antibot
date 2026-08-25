<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Analyzers\CrawlPatternAnalyzer;

beforeEach(function () {
    $this->fakeRedis();
});

function makeCrawlAnalyzer(int $maxPaths = 5, int $enumerationMin = 4, int $enumerationRatio = 3): CrawlPatternAnalyzer
{
    return new CrawlPatternAnalyzer(
        redisConnection: 'default',
        keyPrefix: 'antibot',
        enabled: true,
        windowSeconds: 60,
        maxPaths: $maxPaths,
        enumerationMinRequests: $enumerationMin,
        enumerationRatioThreshold: $enumerationRatio,
        score: 30,
    );
}

it('does not score a normal handful of distinct page views', function () {
    $analyzer = makeCrawlAnalyzer();
    $ip = '203.0.113.20';

    $result = null;
    foreach (['/blog/1', '/blog/2', '/about'] as $path) {
        $result = $analyzer->analyze(makeContext(['ip' => $ip, 'path' => $path]));
    }

    expect($result->score)->toBe(0);
});

it('flags broad crawling once too many distinct paths are visited', function () {
    $analyzer = makeCrawlAnalyzer(maxPaths: 3, enumerationMin: 999, enumerationRatio: 999);
    $ip = '203.0.113.21';

    $result = null;
    foreach (['/a', '/b', '/c', '/d'] as $path) {
        $result = $analyzer->analyze(makeContext(['ip' => $ip, 'path' => $path]));
    }

    expect($result->score)->toBe(30)
        ->and($result->reason)->toBe('high_path_breadth');
});

it('flags sequential enumeration of numeric resource IDs', function () {
    $analyzer = makeCrawlAnalyzer(maxPaths: 999, enumerationMin: 4, enumerationRatio: 3);
    $ip = '203.0.113.22';

    $result = null;
    foreach (['/articles/1', '/articles/2', '/articles/3', '/articles/4'] as $path) {
        $result = $analyzer->analyze(makeContext(['ip' => $ip, 'path' => $path]));
    }

    expect($result->score)->toBe(30)
        ->and($result->reason)->toBe('sequential_enumeration');
});

it('does not flag genuinely diverse browsing as enumeration', function () {
    $analyzer = makeCrawlAnalyzer(maxPaths: 999, enumerationMin: 4, enumerationRatio: 3);
    $ip = '203.0.113.23';

    $result = null;
    foreach (['/articles/1', '/blog/9', '/about', '/contact', '/pricing'] as $path) {
        $result = $analyzer->analyze(makeContext(['ip' => $ip, 'path' => $path]));
    }

    expect($result->score)->toBe(0);
});
