<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Contracts;

/**
 * Centralized DNS abstraction so trusted-bot verifiers never call PHP's
 * DNS functions directly. Production binds SystemDnsResolver; tests bind
 * a fake so verification logic is deterministic and offline.
 */
interface DnsResolver
{
    /**
     * Reverse-resolve an IP to a hostname (PTR lookup), or null on
     * failure/timeout. Must never throw.
     */
    public function reverse(string $ip): ?string;

    /**
     * Forward-resolve a hostname to its IP addresses (A/AAAA), or an
     * empty array on failure/timeout. Must never throw.
     *
     * @return list<string>
     */
    public function resolve(string $hostname): array;
}
