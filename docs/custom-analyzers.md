# Custom Analyzers

An analyzer produces a signal — it never decides ALLOW/CHALLENGE/BLOCK.

```php
namespace App\AntiBot;

use AlisherNPortfolio\LaravelAntiBot\Contracts\Analyzer;
use AlisherNPortfolio\LaravelAntiBot\DTO\AnalyzerResult;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;

final class RefererAnalyzer implements Analyzer
{
    public function analyze(AntiBotContext $context): AnalyzerResult
    {
        if ($context->referer === null && $context->method === 'POST') {
            return new AnalyzerResult(
                analyzer: 'referer',
                score: 10,
                reason: 'missing_referer_on_post',
            );
        }

        return AnalyzerResult::none('referer');
    }
}
```

Register it:

```php
// config/antibot.php
'analyzers' => [
    \AlisherNPortfolio\LaravelAntiBot\Analyzers\RateAnalyzer::class,
    \AlisherNPortfolio\LaravelAntiBot\Analyzers\UserAgentAnalyzer::class,
    \AlisherNPortfolio\LaravelAntiBot\Analyzers\CrawlPatternAnalyzer::class,
    \AlisherNPortfolio\LaravelAntiBot\Analyzers\ChallengeAnalyzer::class,
    \App\AntiBot\RefererAnalyzer::class,
],
```

Analyzers are resolved through Laravel's container (`app()->make($class)`),
so constructor dependencies are injected normally. If your analyzer needs
configuration values, don't hard-code them — read them from your own config
file, or bind the class yourself in your `AppServiceProvider`:

```php
$this->app->singleton(RefererAnalyzer::class, fn () => new RefererAnalyzer(
    strict: config('app.antibot_strict_referer', false),
));
```

## Guidelines

- **Return a score, not a verdict.** A single analyzer should rarely justify
  a hard block by itself — let `RiskScoreEngine`'s thresholds combine
  signals. See `RiskScore` value object for the 0-100 clamped accumulation.
- **Keep it cheap for the common case.** Analyzers run on every non-excluded,
  non-trusted-bot request. If your analyzer needs an expensive lookup, cache
  it (see how `TrustedBotManager` caches DNS results) or gate it behind a
  cheap pre-check.
- **Any Redis you touch should fail loudly, not silently.** If your analyzer
  reads/writes Redis directly, let failures propagate as
  `AlisherNPortfolio\LaravelAntiBot\Support\Exceptions\AntiBotStoreException`
  (see `CrawlPatternAnalyzer` for the pattern) so `AntiBotManager` can apply
  `failure_strategy` consistently, rather than silently returning "no
  signal" regardless of the configured strategy.
- **Never log or expose raw PII.** Hash IPs/session IDs/user agents with
  `AlisherNPortfolio\LaravelAntiBot\Support\Hashing` before including them in
  a reason/metadata payload that might be logged.
