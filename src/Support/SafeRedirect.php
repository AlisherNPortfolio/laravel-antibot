<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Support;

/**
 * Restricts a user-suppliable "redirect back to" value to a same-origin
 * relative path, preventing it from being used as an open-redirect vector
 * (e.g. `//evil.example` or `https://evil.example`).
 */
final class SafeRedirect
{
    public static function sanitize(?string $url, string $fallback = '/'): string
    {
        if ($url === null || $url === '') {
            return $fallback;
        }

        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $fallback;
        }

        if (str_contains($url, '://') || str_contains($url, "\\")) {
            return $fallback;
        }

        return $url;
    }
}
