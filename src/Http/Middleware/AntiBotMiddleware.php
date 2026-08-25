<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Http\Middleware;

use AlisherNPortfolio\LaravelAntiBot\Contracts\AntiBotService;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\DTO\BotDecisionResult;
use AlisherNPortfolio\LaravelAntiBot\Enums\BotDecision;
use AlisherNPortfolio\LaravelAntiBot\Support\AntiBotContextFactory;
use AlisherNPortfolio\LaravelAntiBot\Support\Exceptions\AntiBotStoreException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Request
 *  -> AntiBotContext
 *  -> AntiBotService::inspect() (exclusion -> block check -> trusted bot -> risk engine)
 *  -> ALLOW: next() | CHALLENGE: render the interstitial puzzle page in
 *     place (same URL, so the client's JS can just reload on success) or
 *     JSON for API clients | BLOCK: 403.
 *
 * Not `final`, and every response-building step is a protected method, so
 * a host application can extend this class to fully customize CHALLENGE
 * and BLOCK responses without touching package internals (see
 * docs/configuration.md).
 */
class AntiBotMiddleware
{
    public function __construct(
        private readonly AntiBotService $service,
        private readonly AntiBotContextFactory $contextFactory,
        private readonly string $challengeRouteName = 'antibot.challenge',
        private readonly string $verifyRouteName = 'antibot.verify',
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $context = $this->contextFactory->fromRequest($request);

        try {
            $result = $this->service->inspect($context);
        } catch (AntiBotStoreException $e) {
            // Defense in depth: AntiBotManager already applies failure_strategy
            // internally, but never let an unexpected exception here take the
            // site down regardless.
            Log::warning('antibot.redis_failure', ['message' => $e->getMessage()]);

            return $next($request);
        }

        return match ($result->decision) {
            BotDecision::ALLOW => $next($request),
            BotDecision::CHALLENGE => $this->challengeResponse($request, $context, $result),
            BotDecision::BLOCK => $this->blockResponse($request, $result),
        };
    }

    protected function challengeResponse(Request $request, AntiBotContext $context, BotDecisionResult $result): Response
    {
        if ($context->expectsJson) {
            return response()->json([
                'message' => 'Verification required.',
                'challenge_url' => URL::route($this->challengeRouteName),
            ], 429);
        }

        $challenge = $this->service->createChallenge($context, $result->score);

        return response()->view('antibot::challenge', [
            'challengeId' => $challenge->id,
            'nonce' => $challenge->nonce,
            'difficulty' => $challenge->difficulty,
            'verifyUrl' => URL::route($this->verifyRouteName),
            'redirectUrl' => $request->fullUrl(),
        ], 200);
    }

    protected function blockResponse(Request $request, BotDecisionResult $result): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Access temporarily blocked.',
            ], 403);
        }

        return response()->view('antibot::blocked', [], 403);
    }
}
