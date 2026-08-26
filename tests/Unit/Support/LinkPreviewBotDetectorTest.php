<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Support\LinkPreviewBotDetector;

it('matches a known link-preview bot user agent case-insensitively', function () {
    $detector = new LinkPreviewBotDetector(['telegrambot', 'facebookexternalhit']);

    expect($detector->matches('Mozilla/5.0 (compatible; TelegramBot (like TwitterBot))'))->toBe('telegrambot')
        ->and($detector->matches('facebookexternalhit/1.1'))->toBe('facebookexternalhit');
});

it('returns null for a user agent matching no configured pattern', function () {
    $detector = new LinkPreviewBotDetector(['telegrambot']);

    expect($detector->matches('Mozilla/5.0 Chrome'))->toBeNull();
});

it('returns null when no patterns are configured', function () {
    $detector = new LinkPreviewBotDetector([]);

    expect($detector->matches('TelegramBot (like TwitterBot)'))->toBeNull();
});
