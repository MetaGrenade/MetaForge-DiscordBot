<?php

namespace MetaForge\Discord;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use MetaForge\Discord\Console\Commands\DiscordPingCommand;
use MetaForge\Discord\Console\Commands\SyncDiscordRolesCommand;
use MetaForge\Discord\Http\Middleware\VerifyDiscordBotRequest;
use MetaForge\Discord\Listeners\QueueDiscordRoleSync;
use MetaForge\Discord\Listeners\QueueSupportTicketDiscordNotification;
use MetaForge\Discord\Services\DiscordApiClient;
use MetaForge\Discord\Services\DiscordInboundRoleSyncService;
use MetaForge\Discord\Services\DiscordMemberLookupService;
use MetaForge\Discord\Services\DiscordNotificationPreferenceService;
use MetaForge\Discord\Services\RoleSyncService;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;

class DiscordServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/discord.php', 'discord');

        $this->app->singleton(DiscordApiClient::class, fn () => DiscordApiClient::fromConfig());
        $this->app->singleton(RoleSyncService::class);
        $this->app->singleton(DiscordInboundRoleSyncService::class);
        $this->app->singleton(DiscordNotificationPreferenceService::class);
        $this->app->singleton(DiscordMemberLookupService::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/discord.php' => config_path('discord.php'),
        ], 'metaforge-discord-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadRoutesFrom(__DIR__.'/../routes/discord.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                DiscordPingCommand::class,
                SyncDiscordRolesCommand::class,
            ]);
        }

        Event::listen(RoleAttached::class, [QueueDiscordRoleSync::class, 'handleRoleAttached']);
        Event::listen(RoleDetached::class, [QueueDiscordRoleSync::class, 'handleRoleDetached']);

        foreach ((array) config('discord.support_ticket_event_classes', []) as $eventClass) {
            if (is_string($eventClass) && $eventClass !== '' && class_exists($eventClass)) {
                Event::listen($eventClass, QueueSupportTicketDiscordNotification::class);
            }
        }

        RateLimiter::for('discord-api', function () {
            return Limit::perMinute((int) config('discord.api_rate_limit_per_minute', 50));
        });

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('discord.bot', VerifyDiscordBotRequest::class);

        $this->app->booted(function () {
            if (! config('discord.schedule_stale_sync', true)) {
                return;
            }

            $schedule = $this->app->make(Schedule::class);

            $schedule->command('discord:sync-roles --stale')
                ->hourly()
                ->when(fn () => (bool) config('discord.role_sync_enabled'))
                ->withoutOverlapping();
        });
    }
}
