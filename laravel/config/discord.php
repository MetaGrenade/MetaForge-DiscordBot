<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Discord API
    |--------------------------------------------------------------------------
    */

    'bot_token' => env('DISCORD_BOT_TOKEN'),

    'guild_id' => env('DISCORD_GUILD_ID'),

    'application_id' => env('DISCORD_APPLICATION_ID'),

    'api_base' => rtrim(env('DISCORD_API_BASE', 'https://discord.com/api/v10'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Bot ↔ Laravel authentication
    |--------------------------------------------------------------------------
    */

    'bot_shared_secret' => env('DISCORD_BOT_SHARED_SECRET'),

    'webhook_max_skew_seconds' => (int) env('DISCORD_WEBHOOK_MAX_SKEW', 300),

    /*
    |--------------------------------------------------------------------------
    | Role sync (Spatie → Discord)
    |--------------------------------------------------------------------------
    */

    'role_sync_enabled' => (bool) env('DISCORD_ROLE_SYNC_ENABLED', true),

    'preserve_unmapped_roles' => (bool) env('DISCORD_PRESERVE_UNMAPPED_ROLES', true),

    'social_provider' => env('DISCORD_SOCIAL_PROVIDER', 'discord'),

    /*
    |--------------------------------------------------------------------------
    | Default notification webhook (optional)
    |--------------------------------------------------------------------------
    */

    'default_webhook_url' => env('DISCORD_WEBHOOK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Models (override in host app if needed)
    |--------------------------------------------------------------------------
    */

    'user_model' => env('DISCORD_USER_MODEL', 'App\\Models\\User'),

    'social_account_model' => env('DISCORD_SOCIAL_ACCOUNT_MODEL', 'App\\Models\\SocialAccount'),

];
