<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Support\Hashing;

it('produces a deterministic hash for the same input', function () {
    expect(Hashing::hash('203.0.113.1'))->toBe(Hashing::hash('203.0.113.1'));
});

it('produces different hashes for different inputs', function () {
    expect(Hashing::hash('203.0.113.1'))->not->toBe(Hashing::hash('203.0.113.2'));
});

it('never returns the raw input value', function () {
    expect(Hashing::hash('203.0.113.1'))->not->toBe('203.0.113.1');
});

it('shortHash respects the requested length with a sane minimum', function () {
    expect(Hashing::shortHash('203.0.113.1', 16))->toHaveLength(16)
        ->and(Hashing::shortHash('203.0.113.1', 2))->toHaveLength(8); // clamped to a minimum of 8
});
