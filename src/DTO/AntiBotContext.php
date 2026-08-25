<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\DTO;

/**
 * Framework-independent snapshot of everything the anti-bot engine needs
 * to evaluate a single request. Built once per request by
 * {@see \AlisherNPortfolio\LaravelAntiBot\Support\AntiBotContextFactory}.
 */
final readonly class AntiBotContext
{
    public function __construct(
        public string $ip,
        public string $userAgent,
        public ?string $sessionId,
        public ?string $verificationToken,
        public string $method,
        public string $path,
        public string $routeName,
        public ?string $referer,
        public bool $hasCookies,
        public bool $hasJavascriptVerification,
        public bool $expectsJson,
    ) {}
}
