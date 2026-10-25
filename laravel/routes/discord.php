<?php

use Illuminate\Support\Facades\Route;
use MetaForge\Discord\Http\Controllers\BotEventController;

Route::middleware(['api', 'discord.bot'])
    ->prefix('discord/bot')
    ->group(function () {
        Route::post('events', BotEventController::class);
    });
