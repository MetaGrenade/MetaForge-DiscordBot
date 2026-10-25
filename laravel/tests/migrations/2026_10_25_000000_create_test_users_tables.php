<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('test_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('test_users')->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('provider_id', 191);
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_social_accounts');
        Schema::dropIfExists('test_users');
    }
};
