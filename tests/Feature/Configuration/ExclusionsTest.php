<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->fakeRedis();

    Route::middleware('antibot')->get('/health', fn () => 'healthy')->name('health');
    Route::middleware('antibot')->get('/webhooks/stripe', fn () => 'webhook-ok')->name('webhooks.stripe');
    Route::middleware('antibot')->get('/protected', fn () => 'ok');

    config([
        'antibot.rate_limits' => [
            '10_seconds' => ['seconds' => 10, 'limit' => 1, 'score' => 90],
        ],
    ]);
});

it('never inspects an excluded route name, even under a load that would otherwise block', function () {
    config(['antibot.exclude.routes' => ['health']]);

    $this->get('/health');
    $response = $this->get('/health');

    $response->assertOk()->assertSee('healthy');
});

it('supports wildcard route-name exclusion patterns', function () {
    config(['antibot.exclude.routes' => ['webhooks.*']]);

    $this->get('/webhooks/stripe');
    $response = $this->get('/webhooks/stripe');

    $response->assertOk()->assertSee('webhook-ok');
});

it('never inspects an excluded path pattern', function () {
    config(['antibot.exclude.paths' => ['webhooks/*']]);

    $this->get('/webhooks/stripe');
    $response = $this->get('/webhooks/stripe');

    $response->assertOk()->assertSee('webhook-ok');
});

it('still inspects a route that does not match any exclusion', function () {
    config(['antibot.exclude.routes' => ['health'], 'antibot.exclude.paths' => ['webhooks/*']]);

    $this->get('/protected');
    $response = $this->get('/protected');

    $response->assertStatus(403);
});
