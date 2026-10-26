<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_support_ticket_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->unique();
            $table->string('channel_id', 32);
            $table->string('thread_id', 32)->nullable();
            $table->string('starter_message_id', 32)->nullable();
            $table->string('last_event_type', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_support_ticket_threads');
    }
};
