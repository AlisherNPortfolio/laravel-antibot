<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Services;

use AlisherNPortfolio\LaravelAntiBot\Contracts\AntiBotService;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\DTO\BotDecisionResult;
use AlisherNPortfolio\LaravelAntiBot\DTO\Challenge;
use AlisherNPortfolio\LaravelAntiBot\Enums\BotDecision;
use AlisherNPortfolio\LaravelAntiBot\Support\DatabaseEventRecorder;
use AlisherNPortfolio\LaravelAntiBot\Support\Exceptions\AntiBotStoreException;
use AlisherNPortfolio\LaravelAntiBot\Support\Hashing;
use AlisherNPortfolio\LaravelAntiBot\Support\LinkPreviewBotDetector;
use AlisherNPortfolio\LaravelAntiBot\TrustedBots\TrustedBotManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The package's central orchestrator. Delegates every concern to a
 * focused collaborator instead of implementing it inline:
 *
 *  1. exclusion check
 *  2. temporary block check           -> BlockService
 *  3. trusted crawler verification    -> TrustedBotManager
 *  4. risk analysis                   -> RiskScoreEngine
 *  5. challenge issuance/verification -> ChallengeService
 *
 * A Redis outage anywhere in steps 2-4 is caught once, here, and resolved
 * via the configured `failure_strategy` rather than crashing the request.
 */
final class AntiBotManager implements AntiBotService
{
    /**
     * @param  list<string>  $excludedRouteNames
     * @param  list<string>  $excludedPaths
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly array $excludedRouteNames,
        private readonly array $excludedPaths,
        private readonly BlockService $blockService,
        private readonly TrustedBotManager $trustedBotManager,
        private readonly RiskScoreEngine $riskScoreEngine,
        private readonly ChallengeService $challengeService,
        private readonly string $failureStrategy,
        private readonly bool $loggingEnabled,
        private readonly bool $trustedBotsBypassChallenge,
        private readonly bool $trustedBotsBypassBlock,
        private readonly bool $storeDatabaseEvents,
        private readonly DatabaseEventRecorder $eventRecorder,
        private readonly bool $linkPreviewBotLoggingEnabled,
        private readonly LinkPreviewBotDetector $linkPreviewBotDetector,
    ) {}

    public function inspect(AntiBotContext $context): BotDecisionResult
    {
        $result = $this->resolve($context);

        if ($result->decision !== BotDecision::ALLOW) {
            $this->logLinkPreviewBotIfAffected($context, $result);
        }

        if ($this->storeDatabaseEvents) {
            $this->eventRecorder->record($context, $result->decision->value, $result->score, $result->reason, $result->metadata);
        }

        return $result;
    }

    private function resolve(AntiBotContext $context): BotDecisionResult
    {
        if (! $this->enabled) {
            return BotDecisionResult::allow(reason: 'antibot_disabled');
        }

        if ($this->isExcluded($context)) {
            return BotDecisionResult::allow(reason: 'excluded');
        }

        try {
            if ($this->blockService->isBlocked($context)) {
                return new BotDecisionResult(BotDecision::BLOCK, 100, 'temporarily_blocked');
            }

            $trustedBotResult = $this->trustedBotManager->check($context);

            if ($trustedBotResult->verified) {
                $this->log('antibot.trusted_bot_verified', $context, [
                    'type' => $trustedBotResult->type?->value,
                ]);

                if ($this->trustedBotsBypassChallenge) {
                    return BotDecisionResult::allow(
                        reason: 'trusted_bot_verified',
                        metadata: ['type' => $trustedBotResult->type?->value],
                    );
                }
            }

            if (! $trustedBotResult->verified
                && $trustedBotResult->reason !== null
                && $trustedBotResult->reason !== 'no_trusted_bot_claim') {
                $this->log('antibot.trusted_bot_verification_failed', $context, [
                    'reason' => $trustedBotResult->reason,
                ]);
            }

            $decisionResult = $this->riskScoreEngine->evaluate($context);

            if ($decisionResult->decision === BotDecision::BLOCK) {
                $skipBlock = $trustedBotResult->verified && $this->trustedBotsBypassBlock;

                if (! $skipBlock) {
                    $this->blockService->block($context);
                }

                $this->log('antibot.request_blocked', $context, ['score' => $decisionResult->score]);
            }

            return $decisionResult;
        } catch (AntiBotStoreException $e) {
            Log::warning('antibot.redis_failure', ['message' => $e->getMessage()]);

            return $this->failureStrategy === 'block'
                ? new BotDecisionResult(BotDecision::BLOCK, 100, 'redis_unavailable')
                : BotDecisionResult::allow(reason: 'redis_unavailable_fail_open');
        }
    }

    public function createChallenge(AntiBotContext $context, ?int $riskScore = null): Challenge
    {
        return $this->challengeService->create($context, $riskScore);
    }

    public function verifyChallenge(string $challengeId, string $answer, AntiBotContext $context): bool
    {
        return $this->challengeService->verify($challengeId, $answer, $context);
    }

    private function isExcluded(AntiBotContext $context): bool
    {
        $normalizedPath = ltrim($context->path, '/');

        foreach ($this->excludedPaths as $pattern) {
            if (Str::is(ltrim($pattern, '/'), $normalizedPath)) {
                return true;
            }
        }

        if ($context->routeName !== '') {
            foreach ($this->excludedRouteNames as $pattern) {
                if (Str::is($pattern, $context->routeName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function log(string $event, AntiBotContext $context, array $extra = []): void
    {
        if (! $this->loggingEnabled) {
            return;
        }

        Log::info($event, array_merge([
            'ip_hash' => Hashing::shortHash($context->ip),
            'path' => $context->path,
        ], $extra));
    }

    /**
     * Diagnostic only: a known social/chat link-preview fetcher (Telegram,
     * Facebook, ...) cannot run JavaScript, so a CHALLENGE/BLOCK decision
     * against it breaks that platform's link preview. Never influences the
     * decision itself — see LinkPreviewBotDetector and
     * `antibot.link_preview_bots` config.
     */
    private function logLinkPreviewBotIfAffected(AntiBotContext $context, BotDecisionResult $result): void
    {
        if (! $this->linkPreviewBotLoggingEnabled) {
            return;
        }

        $matchedPattern = $this->linkPreviewBotDetector->matches($context->userAgent);

        if ($matchedPattern === null) {
            return;
        }

        Log::warning('antibot.link_preview_bot_affected', [
            'ip_hash' => Hashing::shortHash($context->ip),
            'path' => $context->path,
            'decision' => $result->decision->value,
            'score' => $result->score,
            'reason' => $result->reason,
            'matched_pattern' => $matchedPattern,
        ]);
    }
}
