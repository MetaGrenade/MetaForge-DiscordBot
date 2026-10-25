<?php

namespace MetaForge\Discord\Tests;

use MetaForge\Discord\DiscordServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            DiscordServiceProvider::class,
            \Spatie\Permission\PermissionServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        config()->set('auth.defaults.guard', 'web');
        config()->set('discord.user_model', TestUser::class);
        config()->set('discord.social_account_model', TestSocialAccount::class);
        config()->set('discord.role_sync_enabled', true);
        config()->set('discord.guild_id', '123456789012345678');
        config()->set('discord.preserve_unmapped_roles', true);
        config()->set('permission.models.role', \Spatie\Permission\Models\Role::class);
        config()->set('permission.models.permission', \Spatie\Permission\Models\Permission::class);
        config()->set('permission.testing', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/migrations');
    }
}
