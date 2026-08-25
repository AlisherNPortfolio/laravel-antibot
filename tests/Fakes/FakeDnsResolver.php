<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Tests\Fakes;

use AlisherNPortfolio\LaravelAntiBot\Contracts\DnsResolver;

/**
 * Deterministic, offline DNS double for tests. Configure expected
 * reverse/forward results with reverse()/willResolve() before use.
 */
final class FakeDnsResolver implements DnsResolver
{
    /** @var array<string, ?string> */
    private array $reverseMap = [];

    /** @var array<string, list<string>> */
    private array $resolveMap = [];

    public function willReverseTo(string $ip, ?string $hostname): self
    {
        $this->reverseMap[$ip] = $hostname;

        return $this;
    }

    /**
     * @param  list<string>  $ips
     */
    public function willResolveTo(string $hostname, array $ips): self
    {
        $this->resolveMap[$hostname] = $ips;

        return $this;
    }

    public function reverse(string $ip): ?string
    {
        return $this->reverseMap[$ip] ?? null;
    }

    public function resolve(string $hostname): array
    {
        return $this->resolveMap[$hostname] ?? [];
    }
}
