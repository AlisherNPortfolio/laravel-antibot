<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Contracts\AntiBotService;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\Enums\BotDecision;
use AlisherNPortfolio\LaravelAntiBot\Http\Middleware\AntiBotMiddleware;
use AlisherNPortfolio\LaravelAntiBot\Services\AntiBotManager;

beforeEach(function () {
    $this->fakeRedis();
});

it('merges the package config', function () {
    expect(config('antibot.enabled'))->toBeTrue()
        ->and(config('antibot.middleware.alias'))->toBe('antibot')
        ->and(config('antibot.decision.allow_max_score'))->toBe(30);
});

it('resolves AntiBotService to AntiBotManager', function () {
    expect($this->app->make(AntiBotService::class))->toBeInstanceOf(AntiBotManager::class);
});

it('registers the antibot middleware alias', function () {
    $router = $this->app['router'];

    expect($router->getMiddleware())
        ->toHaveKey(config('antibot.middleware.alias'), AntiBotMiddleware::class);
});

it('registers the challenge and verify routes under the configured prefix', function () {
    $routes = collect($this->app['router']->getRoutes())->map(fn ($route) => $route->uri());

    expect($routes)->toContain('anti-bot/challenge')
        ->and($routes)->toContain('anti-bot/verify');
});

it('can resolve and run the full inspection pipeline end to end', function () {
    /** @var AntiBotService $service */
    $service = $this->app->make(AntiBotService::class);

    $context = new AntiBotContext(
        ip: '203.0.113.10',
        userAgent: 'Mozilla/5.0 Test Browser',
        sessionId: null,
        verificationToken: null,
        method: 'GET',
        path: '/some/page',
        routeName: '',
        referer: null,
        hasCookies: false,
        hasJavascriptVerification: false,
        expectsJson: false,
    );

    $result = $service->inspect($context);

    expect($result->decision)->toBe(BotDecision::ALLOW);
});
