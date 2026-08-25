<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Support;

use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Optional persistence of one row per inspected request to the
 * `anti_bot_events` table (see database/migrations). Entirely opt-in via
 * `antibot.logging.store_database_events` — the package never requires a
 * database for its core operation, and a failure here is swallowed (with
 * a warning log) rather than allowed to break the request.
 */
final class DatabaseEventRecorder
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        AntiBotContext $context,
        string $decision,
        int $score,
        ?string $reason,
        array $metadata = [],
    ): void {
        try {
            DB::table('anti_bot_events')->insert([
                'ip_hash' => Hashing::hash($context->ip),
                'session_hash' => $context->sessionId !== null ? Hashing::hash($context->sessionId) : null,
                'path' => $context->path,
                'method' => $context->method,
                'user_agent_hash' => $context->userAgent !== '' ? Hashing::hash($context->userAgent) : null,
                'score' => max(0, min(255, $score)),
                'decision' => $decision,
                'reason' => $reason,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('antibot.database_event_failure', ['message' => $e->getMessage()]);
        }
    }
}
