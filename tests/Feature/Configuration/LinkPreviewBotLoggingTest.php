<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->fakeRedis();

    Route::middleware('antibot')->get('/protected', fn () => 'ok');

    // A very aggressive rate limit so a second request is always scored
    // into the CHALLENGE range, regardless of who sends it.
    config([
        'antibot.rate_limits' => [
            '10_seconds' => ['seconds' => 10, 'limit' => 1, 'score' => 50],
        ],
    ]);
});

it('does not log anything when disabled (the default)', function () {
    Log::spy();

    $this->withHeaders(['User-Agent' => 'TelegramBot (like TwitterBot)'])->get('/protected');
    $this->withHeaders(['User-Agent' => 'TelegramBot (like TwitterBot)'])->get('/protected');

    Log::shouldNotHaveReceived('warning');
});

it('logs a distinct event when a known link-preview bot is affected, without changing the decision', function () {
    config(['antibot.link_preview_bots.log_when_affected' => true]);
    Log::spy();

    $this->withHeaders(['User-Agent' => 'TelegramBot (like TwitterBot)'])->get('/protected');
    $response = $this->withHeaders(['User-Agent' => 'TelegramBot (like TwitterBot)'])->get('/protected');

    $response->assertOk()->assertViewIs('antibot::challenge');

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $event, array $context) => $event === 'antibot.link_preview_bot_affected'
            && $context['decision'] === 'challenge'
            && $context['matched_pattern'] === 'telegrambot'
    );
});

it('does not log for an ordinary browser even when enabled', function () {
    config(['antibot.link_preview_bots.log_when_affected' => true]);
    Log::spy();

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome'])->get('/protected');
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome'])->get('/protected');

    Log::shouldNotHaveReceived('warning');
});

it('never bypasses the challenge for a matched link-preview bot even when logging is enabled', function () {
    config(['antibot.link_preview_bots.log_when_affected' => true]);

    $this->withHeaders(['User-Agent' => 'TelegramBot (like TwitterBot)'])->get('/protected');
    $response = $this->withHeaders(['User-Agent' => 'TelegramBot (like TwitterBot)'])->get('/protected');

    $response->assertOk()->assertViewIs('antibot::challenge');
});
