<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Support\SafeRedirect;

it('allows a same-origin relative path', function () {
    expect(SafeRedirect::sanitize('/dashboard'))->toBe('/dashboard');
});

it('falls back for a null or empty value', function () {
    expect(SafeRedirect::sanitize(null))->toBe('/')
        ->and(SafeRedirect::sanitize(''))->toBe('/');
});

it('rejects a protocol-relative URL (open redirect via //host)', function () {
    expect(SafeRedirect::sanitize('//evil.example.com'))->toBe('/');
});

it('rejects an absolute URL to another host', function () {
    expect(SafeRedirect::sanitize('https://evil.example.com/phish'))->toBe('/');
});

it('rejects a value that does not start with a slash', function () {
    expect(SafeRedirect::sanitize('evil.example.com'))->toBe('/');
});

it('rejects a backslash-based bypass attempt', function () {
    expect(SafeRedirect::sanitize('/\\evil.example.com'))->toBe('/');
});

it('honours a custom fallback', function () {
    expect(SafeRedirect::sanitize(null, '/home'))->toBe('/home');
});
