<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Analyzers;

use AlisherNPortfolio\LaravelAntiBot\Contracts\Analyzer;
use AlisherNPortfolio\LaravelAntiBot\DTO\AnalyzerResult;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;

/**
 * Scores suspicious HTTP client signatures. Never causes a hard block by
 * itself — it only ever contributes a signal, per the package's
 * false-positive-averse philosophy.
 *
 * A User-Agent claiming a trusted crawler (Googlebot/Bingbot/YandexBot/...)
 * is handled by TrustedBotManager *before* this analyzer ever runs
 * (verified claims never reach the risk engine at all). If such a claim
 * reaches this analyzer, it already failed DNS verification, so it is
 * scored as a probable spoofing attempt rather than treated as a generic
 * suspicious client. The claimed-name patterns are configuration-driven
 * (`antibot.user_agent.trusted_bot_claim_patterns`) — kept in sync with
 * `TrustedBotType`/the registered verifiers — rather than hard-coded here,
 * so adding a new trusted crawler never means silently forgetting this
 * check (see Architecture.md §15: "Do not hard-code all search engines
 * into the core engine").
 */
final class UserAgentAnalyzer implements Analyzer
{
    /**
     * @param  list<string>  $suspiciousPatterns
     * @param  list<string>  $trustedBotClaimPatterns
     */
    public function __construct(
        private readonly array $suspiciousPatterns,
        private readonly int $suspiciousScore,
        private readonly int $missingUserAgentScore,
        private readonly int $spoofedTrustedBotScore,
        private readonly array $trustedBotClaimPatterns = ['googlebot', 'bingbot', 'yandexbot'],
    ) {}

    public function analyze(AntiBotContext $context): AnalyzerResult
    {
        $userAgent = trim($context->userAgent);

        if ($userAgent === '') {
            return new AnalyzerResult('user_agent', $this->missingUserAgentScore, 'missing_user_agent');
        }

        $lowerUserAgent = strtolower($userAgent);

        foreach ($this->trustedBotClaimPatterns as $pattern) {
            if (str_contains($lowerUserAgent, strtolower($pattern))) {
                return new AnalyzerResult('user_agent', $this->spoofedTrustedBotScore, 'unverified_trusted_bot_claim', [
                    'matched_pattern' => $pattern,
                ]);
            }
        }

        foreach ($this->suspiciousPatterns as $pattern) {
            if (str_contains($lowerUserAgent, strtolower($pattern))) {
                return new AnalyzerResult('user_agent', $this->suspiciousScore, 'suspicious_user_agent', [
                    'matched_pattern' => $pattern,
                ]);
            }
        }

        return AnalyzerResult::none('user_agent');
    }
}
