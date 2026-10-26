<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_forum_crosspost_log', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 191)->unique();
            $table->string('message_id', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_forum_crosspost_log');
    }
};
