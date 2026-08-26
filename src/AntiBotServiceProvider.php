<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot;

use AlisherNPortfolio\LaravelAntiBot\Analyzers\ChallengeAnalyzer;
use AlisherNPortfolio\LaravelAntiBot\Analyzers\CrawlPatternAnalyzer;
use AlisherNPortfolio\LaravelAntiBot\Analyzers\RateAnalyzer;
use AlisherNPortfolio\LaravelAntiBot\Analyzers\UserAgentAnalyzer;
use AlisherNPortfolio\LaravelAntiBot\Console\Commands\PruneAntiBotEventsCommand;
use AlisherNPortfolio\LaravelAntiBot\Contracts\AntiBotService;
use AlisherNPortfolio\LaravelAntiBot\Contracts\BlockStore;
use AlisherNPortfolio\LaravelAntiBot\Contracts\ChallengeProvider;
use AlisherNPortfolio\LaravelAntiBot\Contracts\ChallengeStore;
use AlisherNPortfolio\LaravelAntiBot\Contracts\DnsResolver;
use AlisherNPortfolio\LaravelAntiBot\Contracts\RateLimiter;
use AlisherNPortfolio\LaravelAntiBot\Http\Middleware\AntiBotMiddleware;
use AlisherNPortfolio\LaravelAntiBot\Providers\ProofOfWorkChallengeProvider;
use AlisherNPortfolio\LaravelAntiBot\Services\AntiBotManager;
use AlisherNPortfolio\LaravelAntiBot\Services\BlockService;
use AlisherNPortfolio\LaravelAntiBot\Services\ChallengeFailureTracker;
use AlisherNPortfolio\LaravelAntiBot\Services\ChallengeService;
use AlisherNPortfolio\LaravelAntiBot\Services\RiskScoreEngine;
use AlisherNPortfolio\LaravelAntiBot\Services\VerificationService;
use AlisherNPortfolio\LaravelAntiBot\Stores\RedisBlockStore;
use AlisherNPortfolio\LaravelAntiBot\Stores\RedisChallengeStore;
use AlisherNPortfolio\LaravelAntiBot\Stores\RedisRateLimitStore;
use AlisherNPortfolio\LaravelAntiBot\Support\AntiBotContextFactory;
use AlisherNPortfolio\LaravelAntiBot\Support\DatabaseEventRecorder;
use AlisherNPortfolio\LaravelAntiBot\Support\LinkPreviewBotDetector;
use AlisherNPortfolio\LaravelAntiBot\Support\SystemDnsResolver;
use AlisherNPortfolio\LaravelAntiBot\TrustedBots\TrustedBotManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class AntiBotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/antibot.php', 'antibot');

        $this->registerStores();
        $this->registerTrustedBots();
        $this->registerAnalyzers();
        $this->registerServices();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views/antibot', 'antibot');

        $this->app->make(Router::class)->aliasMiddleware(
            config('antibot.middleware.alias', 'antibot'),
            AntiBotMiddleware::class,
        );

        if (config('antibot.routes.enabled', true)) {
            $this->registerRoutes();
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/antibot.php' => $this->app->configPath('antibot.php'),
            ], 'antibot-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'antibot-migrations');

            $this->publishes([
                __DIR__.'/../resources/js/antibot' => $this->app->publicPath('vendor/antibot/js'),
            ], 'antibot-assets');

            $this->publishes([
                __DIR__.'/../resources/views/antibot' => $this->app->resourcePath('views/vendor/antibot'),
            ], 'antibot-views');

            $this->commands([PruneAntiBotEventsCommand::class]);
        }

        $this->scheduleEventPruning();
    }

    /**
     * Auto-registers a daily run of `antibot:prune-events` on the host
     * app's own scheduler — no Kernel edit required, mirroring how the
     * middleware alias and routes are wired without manual config. Safe
     * to register unconditionally: the command itself no-ops when
     * `retention_days` is disabled or the table doesn't exist, and this
     * only queues an in-memory Schedule entry — it still needs the host's
     * own `* * * * * php artisan schedule:run` cron entry to actually fire,
     * same as any Laravel app.
     */
    private function scheduleEventPruning(): void
    {
        if (! $this->app->bound(Schedule::class)) {
            return;
        }

        $this->app->make(Schedule::class)
            ->command(PruneAntiBotEventsCommand::class)
            ->daily()
            ->name('antibot:prune-events');
    }

    protected function registerRoutes(): void
    {
        $this->app->make(Router::class)->group([
            'prefix' => config('antibot.routes.prefix', 'anti-bot'),
            'middleware' => config('antibot.routes.middleware', ['web']),
        ], __DIR__.'/../routes/antibot.php');
    }

    private function registerStores(): void
    {
        $this->app->singleton(RateLimiter::class, static fn (Application $app) => new RedisRateLimitStore(
            config('antibot.redis.connection', 'default'),
            config('antibot.redis.prefix', 'antibot'),
        ));

        $this->app->singleton(BlockStore::class, static fn (Application $app) => new RedisBlockStore(
            config('antibot.redis.connection', 'default'),
            config('antibot.redis.prefix', 'antibot'),
        ));

        $this->app->singleton(ChallengeStore::class, static fn (Application $app) => new RedisChallengeStore(
            config('antibot.redis.connection', 'default'),
            config('antibot.redis.prefix', 'antibot'),
        ));

        $this->app->singleton(DnsResolver::class, static fn (Application $app) => new SystemDnsResolver(
            (int) config('antibot.trusted_bots.verification.dns_timeout_seconds', 2),
        ));

        $this->app->singleton(ChallengeProvider::class, static fn (Application $app) => new ProofOfWorkChallengeProvider(
            (int) config('antibot.challenge.challenge_ttl_seconds', 60),
        ));

        $this->app->singleton(ChallengeFailureTracker::class, static fn (Application $app) => new ChallengeFailureTracker(
            config('antibot.redis.connection', 'default'),
            config('antibot.redis.prefix', 'antibot'),
        ));
    }

    private function registerTrustedBots(): void
    {
        $this->app->singleton(TrustedBotManager::class, static function (Application $app) {
            /** @var list<class-string<\AlisherNPortfolio\LaravelAntiBot\Contracts\TrustedBotVerifier>> $providerClasses */
            $providerClasses = config('antibot.trusted_bots.providers', []);

            /** @var list<\AlisherNPortfolio\LaravelAntiBot\Contracts\TrustedBotVerifier> $verifiers */
            $verifiers = array_values(array_map(
                static fn (string $class) => $app->make($class),
                $providerClasses,
            ));

            return new TrustedBotManager(
                verifiers: $verifiers,
                enabled: (bool) config('antibot.trusted_bots.enabled', true),
                cacheEnabled: (bool) config('antibot.trusted_bots.cache.enabled', true),
                cacheTtlSeconds: (int) config('antibot.trusted_bots.cache.ttl_seconds', 3600),
                negativeCacheTtlSeconds: (int) config('antibot.trusted_bots.cache.negative_ttl_seconds', 30),
                redisConnection: config('antibot.redis.connection', 'default'),
                keyPrefix: config('antibot.redis.prefix', 'antibot'),
            );
        });
    }

    private function registerAnalyzers(): void
    {
        $this->app->singleton(RateAnalyzer::class, static fn (Application $app) => new RateAnalyzer(
            $app->make(RateLimiter::class),
            config('antibot.rate_limits', []),
        ));

        $this->app->singleton(UserAgentAnalyzer::class, static fn (Application $app) => new UserAgentAnalyzer(
            config('antibot.user_agent.suspicious_patterns', []),
            (int) config('antibot.scoring.suspicious_user_agent', 20),
            (int) config('antibot.scoring.missing_user_agent', 15),
            (int) config('antibot.scoring.spoofed_trusted_bot_claim', 40),
            config('antibot.user_agent.trusted_bot_claim_patterns', ['googlebot', 'bingbot', 'yandexbot']),
        ));

        $this->app->singleton(CrawlPatternAnalyzer::class, static fn (Application $app) => new CrawlPatternAnalyzer(
            config('antibot.redis.connection', 'default'),
            config('antibot.redis.prefix', 'antibot'),
            (bool) config('antibot.crawling.enabled', true),
            (int) config('antibot.crawling.window_seconds', 60),
            (int) config('antibot.crawling.max_paths', 100),
            (int) config('antibot.crawling.enumeration_min_requests', 10),
            (int) config('antibot.crawling.enumeration_ratio_threshold', 5),
            (int) config('antibot.scoring.rapid_crawling', 30),
        ));

        $this->app->singleton(ChallengeAnalyzer::class, static fn (Application $app) => new ChallengeAnalyzer(
            $app->make(ChallengeFailureTracker::class),
            $app->make(VerificationService::class),
            (int) config('antibot.scoring.challenge_failure_1', 20),
            (int) config('antibot.scoring.challenge_failure_2', 30),
            (int) config('antibot.scoring.challenge_failure_3_plus', 50),
            (int) config('antibot.scoring.successful_verification', -30),
        ));
    }

    private function registerServices(): void
    {
        $this->app->singleton(VerificationService::class, static fn (Application $app) => new VerificationService(
            config('antibot.verification.cookie_name', 'antibot_verified'),
            (int) config('antibot.verification.ttl_minutes', 30),
            (bool) config('antibot.verification.secure', true),
            config('antibot.verification.same_site', 'lax'),
        ));

        $this->app->singleton(RiskScoreEngine::class, static function (Application $app) {
            /** @var list<class-string<\AlisherNPortfolio\LaravelAntiBot\Contracts\Analyzer>> $analyzerClasses */
            $analyzerClasses = config('antibot.analyzers', []);

            /** @var list<\AlisherNPortfolio\LaravelAntiBot\Contracts\Analyzer> $analyzers */
            $analyzers = array_values(array_map(
                static fn (string $class) => $app->make($class),
                $analyzerClasses,
            ));

            return new RiskScoreEngine(
                $analyzers,
                (int) config('antibot.decision.allow_max_score', 30),
                (int) config('antibot.decision.challenge_max_score', 70),
            );
        });

        $this->app->singleton(ChallengeService::class, static fn (Application $app) => new ChallengeService(
            $app->make(ChallengeProvider::class),
            $app->make(ChallengeStore::class),
            $app->make(ChallengeFailureTracker::class),
            (int) config('antibot.challenge.default_difficulty', 16),
            (bool) config('antibot.challenge.adaptive_difficulty', true),
            (int) config('antibot.challenge.max_attempts', 3),
            (int) config('antibot.challenge.failure_tracking_ttl_seconds', 900),
            (bool) config('antibot.logging.enabled', true),
        ));

        $this->app->singleton(BlockService::class, static fn (Application $app) => new BlockService(
            $app->make(BlockStore::class),
            (bool) config('antibot.blocking.enabled', true),
            config('antibot.blocking.durations', ['first' => 5, 'second' => 30, 'third' => 60, 'repeat' => 360]),
            (int) config('antibot.blocking.violation_ttl_seconds', 86400),
        ));

        $this->app->singleton(DatabaseEventRecorder::class);

        $this->app->singleton(LinkPreviewBotDetector::class, static fn (Application $app) => new LinkPreviewBotDetector(
            config('antibot.link_preview_bots.patterns', []),
        ));

        $this->app->singleton(AntiBotContextFactory::class, static fn (Application $app) => new AntiBotContextFactory(
            config('antibot.verification.cookie_name', 'antibot_verified'),
        ));

        $this->app->singleton(AntiBotService::class, static fn (Application $app) => new AntiBotManager(
            enabled: (bool) config('antibot.enabled', true),
            excludedRouteNames: config('antibot.exclude.routes', []),
            excludedPaths: config('antibot.exclude.paths', []),
            blockService: $app->make(BlockService::class),
            trustedBotManager: $app->make(TrustedBotManager::class),
            riskScoreEngine: $app->make(RiskScoreEngine::class),
            challengeService: $app->make(ChallengeService::class),
            failureStrategy: config('antibot.failure_strategy', 'allow'),
            loggingEnabled: (bool) config('antibot.logging.enabled', true),
            trustedBotsBypassChallenge: (bool) config('antibot.trusted_bots.bypass_challenge', true),
            trustedBotsBypassBlock: (bool) config('antibot.trusted_bots.bypass_block', true),
            storeDatabaseEvents: (bool) config('antibot.logging.store_database_events', false),
            eventRecorder: $app->make(DatabaseEventRecorder::class),
            linkPreviewBotLoggingEnabled: (bool) config('antibot.link_preview_bots.log_when_affected', false),
            linkPreviewBotDetector: $app->make(LinkPreviewBotDetector::class),
        ));
    }
}
