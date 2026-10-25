<?php

namespace MetaForge\Discord;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use MetaForge\Discord\Console\Commands\DiscordPingCommand;
use MetaForge\Discord\Console\Commands\SyncDiscordRolesCommand;
use MetaForge\Discord\Http\Middleware\VerifyDiscordBotRequest;
use MetaForge\Discord\Listeners\QueueDiscordRoleSync;
use MetaForge\Discord\Services\DiscordApiClient;
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

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('discord.bot', VerifyDiscordBotRequest::class);
    }
}
