<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->fakeRedis();

    Route::middleware('antibot')->get('/protected', fn () => 'ok');
});

it('allows a normal request through to the route', function () {
    $response = $this->get('/protected');

    $response->assertOk()->assertSee('ok');
});

it('renders the interstitial challenge page for a browser request scored into the CHALLENGE range', function () {
    config([
        'antibot.rate_limits' => [
            '10_seconds' => ['seconds' => 10, 'limit' => 1, 'score' => 50],
        ],
    ]);

    $this->get('/protected'); // 1st hit: under the limit, ALLOW
    $response = $this->get('/protected'); // 2nd hit: exceeds it, score 50 -> CHALLENGE

    $response->assertOk()->assertViewIs('antibot::challenge');
});

it('returns a JSON challenge hint for API clients instead of HTML', function () {
    config([
        'antibot.rate_limits' => [
            '10_seconds' => ['seconds' => 10, 'limit' => 1, 'score' => 50],
        ],
    ]);

    $this->getJson('/protected');
    $response = $this->getJson('/protected');

    $response->assertStatus(429)->assertJsonStructure(['message', 'challenge_url']);
});

it('returns 403 for a request scored into the BLOCK range', function () {
    config([
        'antibot.rate_limits' => [
            '10_seconds' => ['seconds' => 10, 'limit' => 1, 'score' => 90],
        ],
    ]);

    $this->get('/protected');
    $response = $this->get('/protected');

    $response->assertStatus(403)->assertViewIs('antibot::blocked');
});

it('returns JSON 403 for a blocked API client', function () {
    config([
        'antibot.rate_limits' => [
            '10_seconds' => ['seconds' => 10, 'limit' => 1, 'score' => 90],
        ],
    ]);

    $this->getJson('/protected');
    $response = $this->getJson('/protected');

    $response->assertStatus(403)->assertJson(['message' => 'Access temporarily blocked.']);
});

it('keeps blocking a client on a subsequent request even after the triggering condition clears', function () {
    config([
        'antibot.rate_limits' => [
            '10_seconds' => ['seconds' => 10, 'limit' => 1, 'score' => 90],
        ],
    ]);

    $this->get('/protected');
    $this->get('/protected')->assertStatus(403); // triggers the block

    // A brand new 10-second rate-limit window (no further hits recorded)
    // would score 0 on its own — but the temporary block persists.
    config(['antibot.rate_limits' => []]);

    $response = $this->get('/protected');

    $response->assertStatus(403);
});

it('passes every request through untouched when the package is disabled', function () {
    config(['antibot.enabled' => false]);
    config([
        'antibot.rate_limits' => [
            '10_seconds' => ['seconds' => 10, 'limit' => 1, 'score' => 90],
        ],
    ]);

    $this->get('/protected');
    $response = $this->get('/protected');

    $response->assertOk()->assertSee('ok');
});
