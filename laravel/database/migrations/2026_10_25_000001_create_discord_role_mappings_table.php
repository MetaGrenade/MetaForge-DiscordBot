<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_role_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('spatie_role_name', 191);
            $table->string('discord_role_id', 32);
            $table->boolean('sync_to_discord')->default(true);
            $table->boolean('sync_from_discord')->default(false);
            $table->timestamps();

            $table->unique('spatie_role_name');
            $table->unique('discord_role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_role_mappings');
    }
};
