<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Tests;

use AlisherNPortfolio\LaravelAntiBot\AntiBotServiceProvider;
use AlisherNPortfolio\LaravelAntiBot\Tests\Fakes\ArrayRedisConnection;
use AlisherNPortfolio\LaravelAntiBot\Tests\Fakes\FakeRedisManager;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AntiBotServiceProvider::class,
        ];
    }

    /**
     * Binds an in-memory fake in place of Laravel's real Redis manager, so
     * every AlisherNPortfolio\LaravelAntiBot component (which always talks
     * to Redis through the `Redis` facade / `redis` container key) works
     * without a real Redis server. Returns the fake connection so a test
     * can inspect or pre-seed its state directly.
     */
    protected function fakeRedis(): ArrayRedisConnection
    {
        $connection = new ArrayRedisConnection;

        $this->app->instance('redis', new FakeRedisManager($connection));

        return $connection;
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');

        // Every test that exercises Redis-backed behaviour calls
        // $this->fakeRedis() explicitly to bind an in-memory double —
        // no real Redis server is required to run this suite.
    }
}
