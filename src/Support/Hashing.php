<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Support;

use Illuminate\Support\Facades\Config;

/**
 * Keyed hashing for anything derived from potentially-identifying data
 * (IP addresses, session ids, user agents) before it is used in a cache
 * key or log line. Keyed with the application key so hashes are not
 * reversible/rainbow-table-able by anyone without it.
 */
final class Hashing
{
    public static function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) Config::get('app.key'));
    }

    /**
     * A short, still-collision-resistant-enough hash for use inside Redis
     * key names, keeping keys compact and bounded in length.
     */
    public static function shortHash(string $value, int $length = 16): string
    {
        return substr(self::hash($value), 0, max(8, $length));
    }
}
