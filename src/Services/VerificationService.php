<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Services;

use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

/**
 * Issues and validates the "already passed a challenge" verification
 * cookie. The token is authenticated-encrypted (Laravel's Crypt facade)
 * independently of the framework's EncryptCookies middleware, so it
 * remains valid whether or not that middleware wraps it a second time —
 * the package never assumes a particular host application middleware
 * stack.
 *
 * Deliberately stateless: no Redis lookup is required to validate a
 * token, keeping ordinary request handling cheap. Deliberately NOT bound
 * to the requester's IP (NAT/mobile/VPN users legitimately change IP
 * mid-session) or to a server-side session record.
 */
final class VerificationService
{
    /** @var ''|'lax'|'none'|'strict' */
    private readonly string $sameSite;

    public function __construct(
        private readonly string $cookieName,
        private readonly int $ttlMinutes,
        private readonly bool $secure,
        string $sameSite,
    ) {
        $this->sameSite = self::normalizeSameSite($sameSite);
    }

    /**
     * @return ''|'lax'|'none'|'strict'
     */
    private static function normalizeSameSite(string $value): string
    {
        return match (strtolower($value)) {
            'strict' => 'strict',
            'none' => 'none',
            '' => '',
            default => 'lax',
        };
    }

    public function cookieName(): string
    {
        return $this->cookieName;
    }

    public function issueCookie(): Cookie
    {
        return new Cookie(
            name: $this->cookieName,
            value: $this->issueToken(),
            expire: time() + ($this->ttlMinutes * 60),
            path: '/',
            domain: null,
            secure: $this->secure,
            httpOnly: true,
            raw: false,
            sameSite: $this->sameSite,
        );
    }

    public function issueToken(): string
    {
        $payload = [
            'iat' => time(),
            'exp' => time() + ($this->ttlMinutes * 60),
            'jti' => bin2hex(random_bytes(16)),
        ];

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function isValid(AntiBotContext $context): bool
    {
        if ($context->verificationToken === null || $context->verificationToken === '') {
            return false;
        }

        return $this->decode($context->verificationToken) !== null;
    }

    /**
     * @return array{iat: int, exp: int, jti: string}|null
     */
    private function decode(string $token): ?array
    {
        try {
            $json = Crypt::decryptString($token);
            /** @var array{iat: int, exp: int, jti: string} $payload */
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (! isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }
}
