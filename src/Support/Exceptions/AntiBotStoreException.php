<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Support\Exceptions;

use RuntimeException;

/**
 * Thrown by Redis-backed stores when the underlying connection/operation
 * fails. Kept as a single package-level exception type (rather than
 * letting Predis/phpredis exceptions leak out) so callers only need to
 * catch one thing to apply the configured `failure_strategy`.
 */
final class AntiBotStoreException extends RuntimeException {}
