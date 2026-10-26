<?php

use Illuminate\Support\Facades\Route;
use MetaForge\Discord\Http\Controllers\Admin\NotificationPreferenceController;
use MetaForge\Discord\Http\Controllers\Admin\RoleMappingController;
use MetaForge\Discord\Http\Controllers\Admin\SyncAdminController;
use MetaForge\Discord\Http\Controllers\BotEventController;
use MetaForge\Discord\Http\Controllers\BotMemberController;
use MetaForge\Discord\Http\Controllers\SupportTicketBotController;

Route::middleware(['api', 'discord.bot'])
    ->prefix('discord/bot')
    ->group(function () {
        Route::post('events', BotEventController::class);
        Route::post('members/link-status', [BotMemberController::class, 'linkStatus']);
        Route::post('members/roles', [BotMemberController::class, 'roles']);
        Route::post('support-tickets', [SupportTicketBotController::class, 'ingest']);
        Route::post('support-tickets/summary', [SupportTicketBotController::class, 'summary']);
    });

Route::middleware(array_merge(config('discord.admin_middleware', ['api']), []))
    ->prefix(config('discord.admin_route_prefix', 'discord/admin'))
    ->group(function () {
        Route::get('role-mappings', [RoleMappingController::class, 'index']);
        Route::post('role-mappings', [RoleMappingController::class, 'store']);
        Route::put('role-mappings/{roleMapping}', [RoleMappingController::class, 'update']);
        Route::delete('role-mappings/{roleMapping}', [RoleMappingController::class, 'destroy']);

        Route::get('sync/status', [SyncAdminController::class, 'status']);
        Route::get('sync/runs', [SyncAdminController::class, 'runs']);
        Route::post('sync/users/{userId}', [SyncAdminController::class, 'syncUser'])
            ->whereNumber('userId');

        Route::get('discord-roles', [SyncAdminController::class, 'discordRoles']);
    });

Route::middleware(array_merge(config('discord.user_middleware', ['api']), []))
    ->prefix(config('discord.user_route_prefix', 'discord/user'))
    ->group(function () {
        Route::get('notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::put('notification-preferences', [NotificationPreferenceController::class, 'update']);
    });
