<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Http\Controllers;

use AlisherNPortfolio\LaravelAntiBot\Contracts\AntiBotService;
use AlisherNPortfolio\LaravelAntiBot\Services\VerificationService;
use AlisherNPortfolio\LaravelAntiBot\Support\AntiBotContextFactory;
use AlisherNPortfolio\LaravelAntiBot\Support\SafeRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;

/**
 * Standalone entry points for the bundled proof-of-work challenge.
 * AntiBotMiddleware normally renders the challenge inline for browser
 * requests, but these routes exist so hosts (or the JSON `challenge_url`
 * hint returned to API clients) can reach/replay the challenge directly.
 */
final class ChallengeController extends Controller
{
    public function __construct(
        private readonly AntiBotService $service,
        private readonly AntiBotContextFactory $contextFactory,
        private readonly VerificationService $verification,
    ) {}

    public function show(Request $request): Response
    {
        $context = $this->contextFactory->fromRequest($request);
        $challenge = $this->service->createChallenge($context);

        $redirectParam = $request->query('redirect');

        return response()->view('antibot::challenge', [
            'challengeId' => $challenge->id,
            'nonce' => $challenge->nonce,
            'difficulty' => $challenge->difficulty,
            'verifyUrl' => URL::route('antibot.verify'),
            'redirectUrl' => SafeRedirect::sanitize(is_string($redirectParam) ? $redirectParam : null),
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge_id' => ['required', 'string', 'max:64'],
            'answer' => ['required', 'string', 'max:32'],
            'redirect' => ['nullable', 'string', 'max:2048'],
        ]);

        $context = $this->contextFactory->fromRequest($request);

        $verified = $this->service->verifyChallenge(
            $validated['challenge_id'],
            $validated['answer'],
            $context,
        );

        if (! $verified) {
            return response()->json([
                'verified' => false,
                'message' => 'Verification failed.',
            ], 422);
        }

        return response()
            ->json([
                'verified' => true,
                'redirect' => SafeRedirect::sanitize($validated['redirect'] ?? null),
            ])
            ->withCookie($this->verification->issueCookie());
    }
}
