<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Providers;

use AlisherNPortfolio\LaravelAntiBot\Contracts\ChallengeProvider;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\DTO\Challenge;

/**
 * Browser proof-of-work: the client must find a `counter` such that
 * SHA256(nonce + counter) has at least `difficulty` leading zero bits.
 * The server never trusts a client-reported difficulty or verified flag —
 * it independently recomputes the hash and re-checks the bit prefix.
 */
final class ProofOfWorkChallengeProvider implements ChallengeProvider
{
    public function __construct(
        private readonly int $ttlSeconds,
    ) {}

    public function create(AntiBotContext $context, int $difficulty): Challenge
    {
        return new Challenge(
            id: bin2hex(random_bytes(16)),
            nonce: bin2hex(random_bytes(16)),
            difficulty: max(1, $difficulty),
            expiresAt: time() + $this->ttlSeconds,
        );
    }

    public function verify(Challenge $challenge, string $answer): bool
    {
        if ($answer === '' || ! preg_match('/^\d+$/', $answer) || strlen($answer) > 20) {
            return false;
        }

        $hash = hash('sha256', $challenge->nonce.$answer);

        return $this->hasLeadingZeroBits($hash, $challenge->difficulty);
    }

    private function hasLeadingZeroBits(string $hexHash, int $requiredZeroBits): bool
    {
        $requiredNibbles = (int) ceil($requiredZeroBits / 4);
        $prefix = substr($hexHash, 0, $requiredNibbles);

        $binary = '';

        foreach (str_split($prefix) as $hexDigit) {
            $binary .= str_pad(base_convert($hexDigit, 16, 2), 4, '0', STR_PAD_LEFT);
        }

        return substr($binary, 0, $requiredZeroBits) === str_repeat('0', $requiredZeroBits);
    }
}
