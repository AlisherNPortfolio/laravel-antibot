<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Contracts\DnsResolver;
use AlisherNPortfolio\LaravelAntiBot\Tests\Fakes\FakeDnsResolver;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->fakeRedis();

    Route::middleware('antibot')->get('/protected', fn () => 'ok');

    // A very aggressive rate limit so that, without trusted-bot bypass,
    // even a couple of requests would normally be CHALLENGE/BLOCK-worthy.
    config([
        'antibot.rate_limits' => [
            '10_seconds' => ['seconds' => 10, 'limit' => 1, 'score' => 90],
        ],
    ]);
});

it('lets a verified Googlebot through even while other clients would be blocked', function () {
    $dns = (new FakeDnsResolver)
        ->willReverseTo('66.249.66.1', 'crawl-66-249-66-1.googlebot.com')
        ->willResolveTo('crawl-66-249-66-1.googlebot.com', ['66.249.66.1']);

    $this->app->bind(DnsResolver::class, fn () => $dns);

    $headers = ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'];

    for ($i = 0; $i < 5; $i++) {
        $response = $this->withHeaders($headers)
            ->withServerVariables(['REMOTE_ADDR' => '66.249.66.1'])
            ->get('/protected');
    }

    $response->assertOk()->assertSee('ok');
});

it('subjects a fake Googlebot (failed DNS verification) to normal risk analysis and eventually blocks it', function () {
    // FakeDnsResolver with nothing configured -> every lookup fails, so the
    // claimed hostname never verifies.
    $this->app->bind(DnsResolver::class, fn () => new FakeDnsResolver);

    $headers = ['User-Agent' => 'Googlebot/2.1'];

    $this->withHeaders($headers)->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])->get('/protected');
    $response = $this->withHeaders($headers)->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])->get('/protected');

    $response->assertStatus(403);
});

it('never lets DNS unavailability take down normal traffic', function () {
    // No PTR configured at all -> reverse() returns null -> not trusted ->
    // falls through to ordinary risk analysis, which still ALLOWs a single
    // unremarkable request.
    $this->app->bind(DnsResolver::class, fn () => new FakeDnsResolver);

    $response = $this->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome'])->get('/protected');

    $response->assertOk()->assertSee('ok');
});
