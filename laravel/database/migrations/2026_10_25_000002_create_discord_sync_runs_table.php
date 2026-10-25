<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('trigger', 50);
            $table->string('status', 20);
            $table->unsignedInteger('users_processed')->default(0);
            $table->unsignedInteger('users_synced')->default(0);
            $table->unsignedInteger('users_skipped')->default(0);
            $table->unsignedInteger('users_failed')->default(0);
            $table->json('meta')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_sync_runs');
    }
};
