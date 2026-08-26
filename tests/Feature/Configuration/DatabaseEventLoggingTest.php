<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Contracts\DnsResolver;
use AlisherNPortfolio\LaravelAntiBot\Tests\Fakes\FakeDnsResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->fakeRedis();

    config(['database.default' => 'sqlite']);
    config(['database.connections.sqlite.database' => ':memory:']);
    (require __DIR__.'/../../../database/migrations/create_anti_bot_events_table.php')->up();

    config(['antibot.logging.store_database_events' => true]);

    Route::middleware('antibot')->get('/protected', fn () => 'ok');
});

afterEach(function () {
    Schema::dropIfExists('anti_bot_events');
});

it('persists the per-analyzer metadata, not just the decision and reason', function () {
    config([
        'antibot.rate_limits' => [
            '10_seconds' => ['seconds' => 10, 'limit' => 1, 'score' => 50],
        ],
    ]);

    $this->get('/protected');
    $this->get('/protected'); // 2nd hit exceeds the limit -> CHALLENGE

    $event = DB::table('anti_bot_events')->latest('id')->first();

    expect($event->decision)->toBe('challenge');

    $metadata = json_decode($event->metadata, true);

    expect($metadata)->toHaveKey('rate')
        ->and($metadata['rate']['reason'])->toBe('rate_limit_exceeded')
        ->and($metadata['rate']['metadata'])->toHaveKey('windows');
});

it('persists the verified trusted-bot type for an ALLOW decision', function () {
    $dns = (new FakeDnsResolver)
        ->willReverseTo('66.249.66.1', 'crawl-66-249-66-1.googlebot.com')
        ->willResolveTo('crawl-66-249-66-1.googlebot.com', ['66.249.66.1']);

    $this->app->bind(DnsResolver::class, fn () => $dns);

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])
        ->withServerVariables(['REMOTE_ADDR' => '66.249.66.1'])
        ->get('/protected');

    $event = DB::table('anti_bot_events')->latest('id')->first();

    expect($event->decision)->toBe('allow');

    $metadata = json_decode($event->metadata, true);

    expect($metadata)->toBe(['type' => 'googlebot']);
});
