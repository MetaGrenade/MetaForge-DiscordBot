<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_guilds', function (Blueprint $table) {
            $table->id();
            $table->string('guild_id', 32)->unique();
            $table->string('label')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['enabled', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_guilds');
    }
};
