<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entirely optional. The package never requires this table for core
 * operation — only enable via config('antibot.logging.store_database_events').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anti_bot_events', function (Blueprint $table) {
            $table->id();
            $table->string('ip_hash', 64)->index();
            $table->string('session_hash', 64)->nullable();
            $table->string('path');
            $table->string('method', 10);
            $table->string('user_agent_hash', 64)->nullable();
            $table->unsignedTinyInteger('score');
            $table->string('decision', 16)->index();
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anti_bot_events');
    }
};
