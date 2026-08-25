<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Contracts;

/**
 * A sliding-window hit counter. Implementations must be atomic (safe under
 * concurrent requests for the same key) and must bound the storage they
 * use to roughly `$windowSeconds` worth of entries per key.
 */
interface RateLimiter
{
    /**
     * Record one hit for the given key and return the number of hits
     * that have occurred within the trailing `$windowSeconds` window,
     * including this one.
     */
    public function hit(string $key, int $windowSeconds): int;
}
