<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('antibot')->get('/protected', fn () => 'ok');

    // Simulate a Redis outage: every connection() call throws.
    $this->app->instance('redis', new class
    {
        public function connection($name = null)
        {
            throw new RuntimeException('Connection refused');
        }
    });
});

it('fails open (ALLOW) by default when Redis is unavailable', function () {
    config(['antibot.failure_strategy' => 'allow']);

    $response = $this->get('/protected');

    $response->assertOk()->assertSee('ok');
});

it('fails closed (BLOCK) when configured to do so', function () {
    // A deliberate operator trade-off: the temporary-block check itself
    // needs Redis and runs before trusted-bot verification (per the
    // documented decision flow), so 'block' fails closed for every
    // client — including crawlers — during a genuine infrastructure
    // outage. This is intentionally different from a *DNS* failure
    // (see TrustedBotManagerTest), which never blocks anyone because
    // trusted-bot verification does not depend on Redis at all.
    config(['antibot.failure_strategy' => 'block']);

    $response = $this->get('/protected');

    $response->assertStatus(403);
});
