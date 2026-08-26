<?php

declare(strict_types=1);

namespace AlisherNPortfolio\LaravelAntiBot\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deletes `anti_bot_events` rows older than `antibot.logging.retention_days`.
 * Entirely optional and safe to run even when the table was never
 * published/migrated, or when database event logging was never enabled —
 * both cases are treated as "nothing to prune" rather than an error, since
 * an idle prune command must never break a host application's scheduler.
 */
final class PruneAntiBotEventsCommand extends Command
{
    protected $signature = 'antibot:prune-events';

    protected $description = 'Delete anti_bot_events rows older than antibot.logging.retention_days';

    public function handle(): int
    {
        $retentionDays = (int) config('antibot.logging.retention_days', 0);

        if ($retentionDays <= 0) {
            $this->components->info('antibot.logging.retention_days is not set (or <= 0) — pruning is disabled, nothing to do.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('anti_bot_events')) {
            $this->components->info('The anti_bot_events table does not exist — nothing to prune.');

            return self::SUCCESS;
        }

        $deleted = DB::table('anti_bot_events')
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->delete();

        $this->components->info("Deleted {$deleted} anti_bot_events row(s) older than {$retentionDays} day(s).");

        return self::SUCCESS;
    }
}
