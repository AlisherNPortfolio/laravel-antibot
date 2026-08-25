<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\Services\VerificationService;
use Illuminate\Support\Facades\Crypt;

it('validates a freshly issued token', function () {
    $service = new VerificationService('antibot_verified', 30, true, 'lax');
    $token = $service->issueToken();

    $context = makeContext(['verificationToken' => $token]);

    expect($service->isValid($context))->toBeTrue();
});

it('rejects a missing token', function () {
    $service = new VerificationService('antibot_verified', 30, true, 'lax');

    expect($service->isValid(makeContext(['verificationToken' => null])))->toBeFalse();
});

it('rejects a tampered/garbage token', function () {
    $service = new VerificationService('antibot_verified', 30, true, 'lax');

    expect($service->isValid(makeContext(['verificationToken' => 'garbage'])))->toBeFalse();
});

it('rejects an expired token', function () {
    $service = new VerificationService('antibot_verified', 30, true, 'lax');

    $token = Crypt::encryptString(json_encode([
        'iat' => time() - 7200,
        'exp' => time() - 3600,
        'jti' => 'x',
    ]));

    expect($service->isValid(makeContext(['verificationToken' => $token])))->toBeFalse();
});

it('produces a cookie with the configured name and security attributes', function () {
    $service = new VerificationService('antibot_verified', 30, true, 'strict');
    $cookie = $service->issueCookie();

    expect($cookie->getName())->toBe('antibot_verified')
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('strict');
});
