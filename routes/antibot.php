<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Http\Controllers\ChallengeController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
 * Registered by AntiBotServiceProvider under the prefix/middleware from
 * config('antibot.routes'), only when config('antibot.routes.enabled') is
 * true. Deliberately NOT wrapped in the 'antibot' middleware itself, to
 * avoid challenging the challenge endpoint.
 */

Route::get('/challenge', [ChallengeController::class, 'show'])
    ->middleware('throttle:'.config('antibot.challenge.route_rate_limit', '60,1'))
    ->name('antibot.challenge');

// The client's plain fetch() POST never carries a CSRF token, and forging
// this request gains an attacker nothing (it can only set the *victim's
// own* browser cookie), so it is intentionally exempt from CSRF
// validation rather than requiring every host application to special-case it.
Route::post('/verify', [ChallengeController::class, 'verify'])
    ->middleware('throttle:'.config('antibot.challenge.verify_rate_limit', '20,1'))
    ->withoutMiddleware(ValidateCsrfToken::class)
    ->name('antibot.verify');
