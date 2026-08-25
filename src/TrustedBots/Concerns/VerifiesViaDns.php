<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\TrustedBots\Concerns;

use AlisherNPortfolio\LaravelAntiBot\Contracts\DnsResolver;

/**
 * Shared reverse+forward DNS confirmation used by every trusted crawler
 * verifier: never trust the User-Agent, only a hostname that genuinely
 * belongs to the crawler's official domain and whose forward lookup
 * resolves back to the original request IP.
 */
trait VerifiesViaDns
{
    /**
     * @param  list<string>  $allowedHostnameSuffixes
     * @return array{verified: bool, hostname: ?string, reason: ?string}
     */
    protected function verifyHostnameOwnership(
        DnsResolver $resolver,
        string $ip,
        array $allowedHostnameSuffixes,
    ): array {
        $hostname = $resolver->reverse($ip);

        if ($hostname === null || $hostname === '') {
            return ['verified' => false, 'hostname' => null, 'reason' => 'reverse_dns_failed'];
        }

        $normalizedHostname = rtrim(strtolower($hostname), '.');

        $matchesTrustedSuffix = false;

        foreach ($allowedHostnameSuffixes as $suffix) {
            if ($this->hostnameMatchesSuffix($normalizedHostname, $suffix)) {
                $matchesTrustedSuffix = true;
                break;
            }
        }

        if (! $matchesTrustedSuffix) {
            return ['verified' => false, 'hostname' => $hostname, 'reason' => 'hostname_suffix_mismatch'];
        }

        $forwardIps = $resolver->resolve($normalizedHostname);

        if (! in_array($ip, $forwardIps, true)) {
            return ['verified' => false, 'hostname' => $hostname, 'reason' => 'forward_dns_mismatch'];
        }

        return ['verified' => true, 'hostname' => $hostname, 'reason' => null];
    }

    /**
     * A strict suffix match on a dot boundary: "googlebot.com" matches
     * itself and "crawl-1-2-3-4.googlebot.com", but NOT
     * "evilgooglebot.com" — a naive str_ends_with() would wrongly accept
     * that as a bypass.
     */
    private function hostnameMatchesSuffix(string $hostname, string $suffix): bool
    {
        $suffix = strtolower($suffix);

        return $hostname === $suffix || str_ends_with($hostname, '.'.$suffix);
    }
}
