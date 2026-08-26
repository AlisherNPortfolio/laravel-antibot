<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config(['database.default' => 'sqlite']);
    config(['database.connections.sqlite.database' => ':memory:']);
});

afterEach(function () {
    if (Schema::hasTable('anti_bot_events')) {
        Schema::dropIfExists('anti_bot_events');
    }
});

function seedAntiBotEvent(string $createdAt): void
{
    DB::table('anti_bot_events')->insert([
        'ip_hash' => 'hash-'.$createdAt,
        'path' => '/protected',
        'method' => 'GET',
        'score' => 10,
        'decision' => 'allow',
        'created_at' => $createdAt,
    ]);
}

it('deletes rows older than the configured retention period and keeps the rest', function () {
    (require __DIR__.'/../../../database/migrations/create_anti_bot_events_table.php')->up();

    seedAntiBotEvent(now()->subDays(40)->toDateTimeString());
    seedAntiBotEvent(now()->subDays(10)->toDateTimeString());
    seedAntiBotEvent(now()->subDays(1)->toDateTimeString());

    config(['antibot.logging.retention_days' => 30]);

    $this->artisan('antibot:prune-events')->assertExitCode(0);

    expect(DB::table('anti_bot_events')->count())->toBe(2);
});

it('does nothing when retention_days is disabled (0)', function () {
    (require __DIR__.'/../../../database/migrations/create_anti_bot_events_table.php')->up();

    seedAntiBotEvent(now()->subDays(999)->toDateTimeString());

    config(['antibot.logging.retention_days' => 0]);

    $this->artisan('antibot:prune-events')->assertExitCode(0);

    expect(DB::table('anti_bot_events')->count())->toBe(1);
});

it('never fails when the anti_bot_events table has not been migrated', function () {
    config(['antibot.logging.retention_days' => 30]);

    expect(Schema::hasTable('anti_bot_events'))->toBeFalse();

    $this->artisan('antibot:prune-events')->assertExitCode(0);
});
