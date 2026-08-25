<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Analyzers;

use AlisherNPortfolio\LaravelAntiBot\Contracts\Analyzer;
use AlisherNPortfolio\LaravelAntiBot\DTO\AnalyzerResult;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\Stores\Concerns\InteractsWithRedis;
use AlisherNPortfolio\LaravelAntiBot\Support\Hashing;

/**
 * Detects generic crawling behaviour without ever hard-coding a
 * domain-specific path. Two independent, path-agnostic signals are used:
 *
 * - Breadth: too many distinct paths visited within the window
 *   (e.g. broad sweeping/scraping across many resources).
 * - Enumeration: many distinct raw paths collapsing onto very few
 *   "shapes" once numeric segments are normalized away (e.g.
 *   /articles/1, /articles/2, /articles/3, ... all normalize to
 *   /articles/{n}) — a classic sequential-resource-enumeration pattern.
 *
 * A single sequential request is never enough by itself; both signals
 * require a minimum volume before contributing to the score, matching
 * the package's false-positive-averse philosophy.
 */
final class CrawlPatternAnalyzer implements Analyzer
{
    use InteractsWithRedis;

    public function __construct(
        private readonly string $redisConnection,
        private readonly string $keyPrefix,
        private readonly bool $enabled,
        private readonly int $windowSeconds,
        private readonly int $maxPaths,
        private readonly int $enumerationMinRequests,
        private readonly int $enumerationRatioThreshold,
        private readonly int $score,
    ) {}

    public function analyze(AntiBotContext $context): AnalyzerResult
    {
        if (! $this->enabled) {
            return AnalyzerResult::none('crawl_pattern');
        }

        $identity = Hashing::shortHash($context->ip);
        $pathsKey = $this->key("crawl:{$identity}:paths");
        $shapesKey = $this->key("crawl:{$identity}:shapes");
        $shape = $this->normalizePath($context->path);

        [$pathCount, $shapeCount] = $this->guarded(function ($redis) use ($pathsKey, $shapesKey, $context, $shape) {
            $redis->sadd($pathsKey, $context->path);
            $redis->expire($pathsKey, $this->windowSeconds);
            $redis->sadd($shapesKey, $shape);
            $redis->expire($shapesKey, $this->windowSeconds);

            return [
                (int) $redis->scard($pathsKey),
                (int) $redis->scard($shapesKey),
            ];
        });

        if ($pathCount > $this->maxPaths) {
            return new AnalyzerResult('crawl_pattern', $this->score, 'high_path_breadth', [
                'unique_paths' => $pathCount,
            ]);
        }

        $isEnumerating = $pathCount >= $this->enumerationMinRequests
            && $shapeCount > 0
            && ($pathCount / $shapeCount) >= $this->enumerationRatioThreshold;

        if ($isEnumerating) {
            return new AnalyzerResult('crawl_pattern', $this->score, 'sequential_enumeration', [
                'unique_paths' => $pathCount,
                'unique_shapes' => $shapeCount,
            ]);
        }

        return AnalyzerResult::none('crawl_pattern');
    }

    private function normalizePath(string $path): string
    {
        return (string) preg_replace('/\d+/', '{n}', $path);
    }
}
