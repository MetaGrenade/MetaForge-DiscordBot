<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_sync_failures', function (Blueprint $table) {
            $table->id();
            $table->string('operation', 64);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('discord_user_id', 32)->nullable();
            $table->string('guild_id', 32)->nullable();
            $table->json('payload')->nullable();
            $table->text('error');
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->timestamp('last_failed_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['operation', 'resolved_at']);
            $table->index('user_id');
            $table->index('last_failed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_sync_failures');
    }
};
