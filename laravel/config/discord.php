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

    /*
    |--------------------------------------------------------------------------
    | Role sync (Discord → app, phase 3)
    |--------------------------------------------------------------------------
    |
    | Mappings with sync_from_discord=true mirror Discord role membership into
    | Spatie when guildMemberUpdate is forwarded by the gateway bot.
    |
    | inbound_conflict_policy:
    |   discord_wins — Discord role changes update Spatie for mirrored mappings.
    |   app_wins     — ignore inbound Discord changes (app → Discord only).
    |
    */

    'sync_from_discord_enabled' => (bool) env('DISCORD_SYNC_FROM_DISCORD_ENABLED', true),

    'inbound_conflict_policy' => env('DISCORD_INBOUND_CONFLICT_POLICY', 'discord_wins'),

    'inbound_sync_queue' => (bool) env('DISCORD_INBOUND_SYNC_QUEUE', true),

    'preserve_unmapped_roles' => (bool) env('DISCORD_PRESERVE_UNMAPPED_ROLES', true),

    'role_sync_chunk_size' => (int) env('DISCORD_ROLE_SYNC_CHUNK_SIZE', 100),

    'api_retry_times' => (int) env('DISCORD_API_RETRY_TIMES', 5),

    'api_rate_limit_per_minute' => (int) env('DISCORD_API_RATE_LIMIT_PER_MINUTE', 50),

    'stale_sync_hours' => (int) env('DISCORD_STALE_SYNC_HOURS', 24),

    'schedule_stale_sync' => (bool) env('DISCORD_SCHEDULE_STALE_SYNC', true),

    'social_provider' => env('DISCORD_SOCIAL_PROVIDER', 'discord'),

    /*
    |--------------------------------------------------------------------------
    | Default notification webhook (optional)
    |--------------------------------------------------------------------------
    */

    'default_webhook_url' => env('DISCORD_WEBHOOK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Notification opt-in categories (user settings + DiscordNotifiableNotification)
    |--------------------------------------------------------------------------
    */

    'notification_categories' => [
        'support_ticket_reply' => 'Support ticket replies',
        'order_shipped' => 'Order shipped updates',
        'account_moderation' => 'Account moderation notices',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP routes (middleware applied in host app context)
    |--------------------------------------------------------------------------
    */

    'admin_route_prefix' => env('DISCORD_ADMIN_ROUTE_PREFIX', 'discord/admin'),

    /*
    | Host apps MUST add authentication/authorization middleware (e.g. auth:sanctum,
    | can:manage-discord) — the package ships with api-only defaults so routes fail
    | open until the host locks them down. See docs/security.md.
    */
    'admin_middleware' => ['api'],

    'user_route_prefix' => env('DISCORD_USER_ROUTE_PREFIX', 'discord/user'),

    'user_middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Support ticket hooks (phase 4 subset)
    |--------------------------------------------------------------------------
    |
    | Fully-qualified event class names from the host MetaForge app. When fired,
    | a thin job posts to DISCORD_WEBHOOK_URL (stub until thread mirroring lands).
    |
    */

    'support_ticket_event_classes' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('DISCORD_SUPPORT_TICKET_EVENT_CLASSES', '')),
    ))),

    'support_ticket_mirror_enabled' => (bool) env('DISCORD_SUPPORT_TICKET_MIRROR_ENABLED', true),

    'support_ticket_channel_id' => env('DISCORD_SUPPORT_TICKET_CHANNEL_ID'),

    'support_ticket_webhook_url' => env('DISCORD_SUPPORT_TICKET_WEBHOOK_URL'),

    'support_ticket_thread_per_ticket' => (bool) env('DISCORD_SUPPORT_TICKET_THREAD_PER_TICKET', false),

    'support_ticket_thread_auto_archive_minutes' => (int) env('DISCORD_SUPPORT_TICKET_THREAD_AUTO_ARCHIVE', 1440),

    'support_ticket_url_template' => env('DISCORD_SUPPORT_TICKET_URL_TEMPLATE'),

    'support_ticket_excerpt_max' => (int) env('DISCORD_SUPPORT_TICKET_EXCERPT_MAX', 280),

    'support_staff_discord_role_ids' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('DISCORD_SUPPORT_STAFF_ROLE_IDS', '')),
    ))),

    'support_staff_permissions' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('DISCORD_SUPPORT_STAFF_PERMISSIONS', '')),
    ))),

    'support_staff_spatie_roles' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('DISCORD_SUPPORT_STAFF_SPATIE_ROLES', 'admin,moderator')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Forum / announcement cross-post (phase 4)
    |--------------------------------------------------------------------------
    */

    'forum_crosspost_enabled' => (bool) env('DISCORD_FORUM_CROSSPOST_ENABLED', false),

    'forum_crosspost_channel_id' => env('DISCORD_FORUM_CROSSPOST_CHANNEL_ID'),

    'forum_crosspost_webhook_url' => env('DISCORD_FORUM_CROSSPOST_WEBHOOK_URL'),

    'forum_crosspost_event_classes' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('DISCORD_FORUM_CROSSPOST_EVENT_CLASSES', 'App\\Events\\ForumPostCreated')),
    ))),

    'forum_crosspost_mode' => env('DISCORD_FORUM_CROSSPOST_MODE', 'new_threads'),

    'forum_crosspost_board_slugs' => env('DISCORD_FORUM_CROSSPOST_BOARD_SLUGS'),

    'forum_crosspost_url_template' => env('DISCORD_FORUM_CROSSPOST_URL_TEMPLATE'),

    'forum_rss_url' => env('DISCORD_FORUM_RSS_URL'),

    /*
    |--------------------------------------------------------------------------
    | Welcome messages (optional)
    |--------------------------------------------------------------------------
    */

    'welcome_enabled' => (bool) env('DISCORD_WELCOME_ENABLED', false),

    'welcome_channel_id' => env('DISCORD_WELCOME_CHANNEL_ID'),

    'welcome_linked_only' => (bool) env('DISCORD_WELCOME_LINKED_ONLY', true),

    'welcome_message_template' => env(
        'DISCORD_WELCOME_MESSAGE_TEMPLATE',
        'Welcome <@{discord_user_id}> — link your MetaForge account via Discord OAuth on the site.',
    ),

    /*
    |--------------------------------------------------------------------------
    | Outbound Discord posts (webhooks + bot API)
    |--------------------------------------------------------------------------
    */

    'outbound_rate_limit_per_minute' => (int) env('DISCORD_OUTBOUND_RATE_LIMIT_PER_MINUTE', 30),

    /*
    |--------------------------------------------------------------------------
    | Models (override in host app if needed)
    |--------------------------------------------------------------------------
    */

    'user_model' => env('DISCORD_USER_MODEL', 'App\\Models\\User'),

    'social_account_model' => env('DISCORD_SOCIAL_ACCOUNT_MODEL', 'App\\Models\\SocialAccount'),

];
