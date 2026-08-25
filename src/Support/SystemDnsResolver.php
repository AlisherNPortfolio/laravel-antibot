<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Support;

use AlisherNPortfolio\LaravelAntiBot\Contracts\DnsResolver;
use Throwable;

/**
 * Production DNS resolver backed by PHP's native resolver functions.
 *
 * Timeout limitation: PHP has no cross-platform, per-call DNS timeout API.
 * This class sets `default_socket_timeout` around each lookup as a
 * best-effort guard, but PHP's resolver on some platforms/builds may not
 * honor it and can still block briefly beyond the configured value. For a
 * hard guarantee, configure a short resolver timeout at the OS level
 * (e.g. `options timeout:1 attempts:1` in `/etc/resolv.conf` on Linux).
 * See docs/trusted-bots.md for details.
 */
final class SystemDnsResolver implements DnsResolver
{
    public function __construct(
        private readonly int $timeoutSeconds = 2,
    ) {}

    public function reverse(string $ip): ?string
    {
        return $this->withTimeout(function () use ($ip): ?string {
            $host = @gethostbyaddr($ip);

            if ($host === false || $host === $ip) {
                return null;
            }

            return $host;
        });
    }

    public function resolve(string $hostname): array
    {
        return $this->withTimeout(function () use ($hostname): array {
            $records = @dns_get_record($hostname, DNS_A + DNS_AAAA);

            if ($records === false || $records === []) {
                return [];
            }

            $ips = [];

            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $ips[] = $record['ip'];
                }

                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }

            return array_values(array_unique($ips));
        }) ?? [];
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $operation
     * @return TReturn|null
     */
    private function withTimeout(callable $operation): mixed
    {
        $previous = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', (string) max(1, $this->timeoutSeconds));

        try {
            return $operation();
        } catch (Throwable) {
            return null;
        } finally {
            ini_set('default_socket_timeout', $previous === false ? '60' : $previous);
        }
    }
}
