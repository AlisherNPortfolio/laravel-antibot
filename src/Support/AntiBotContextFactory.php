<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Support;

use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use Illuminate\Http\Request;

/**
 * Converts a Laravel Request into the framework-independent AntiBotContext
 * that the rest of the package operates on. Keeping this conversion in one
 * place means every internal component sees the same, already-normalized
 * view of the request.
 */
final class AntiBotContextFactory
{
    public function __construct(
        private readonly string $verificationCookieName,
    ) {}

    public function fromRequest(Request $request): AntiBotContext
    {
        $route = $request->route();
        $verificationCookie = $request->cookie($this->verificationCookieName);

        return new AntiBotContext(
            ip: (string) $request->ip(),
            userAgent: (string) ($request->userAgent() ?? ''),
            sessionId: $request->hasSession() ? $request->session()->getId() : null,
            verificationToken: is_string($verificationCookie) ? $verificationCookie : null,
            method: $request->method(),
            path: '/'.ltrim($request->path(), '/'),
            routeName: $route?->getName() ?? '',
            referer: $request->headers->get('referer'),
            hasCookies: $request->cookies->count() > 0,
            hasJavascriptVerification: $verificationCookie !== null,
            expectsJson: $request->expectsJson(),
        );
    }
}
