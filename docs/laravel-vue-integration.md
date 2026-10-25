# MetaForge (laravel-vue) integration — Phase 2

Wire the `metagrenade/metaforge-discord` package admin API and user notification settings into the MetaForge ACP and account settings UI.

## 1. Middleware and authorization

Publish config if needed, then in `config/discord.php` (or `.env`) set middleware stacks that match MetaForge auth:

```php
'admin_middleware' => ['web', 'auth', 'verified', 'can:access-acp'],
'user_middleware' => ['web', 'auth', 'verified'],
```

Replace `can:access-acp` with your existing ACP gate/permission. All admin routes are JSON under the prefix `discord/admin` (configurable via `DISCORD_ADMIN_ROUTE_PREFIX`).

User notification preference routes live under `discord/user/notification-preferences`.

## 2. ACP — role mappings

Suggested Inertia pages (Vue) calling the package API with your HTTP client (axios/Inertia router):

| Action | Method | Path |
|--------|--------|------|
| List mappings | `GET` | `/discord/admin/role-mappings` |
| Create | `POST` | `/discord/admin/role-mappings` |
| Update | `PUT` | `/discord/admin/role-mappings/{id}` |
| Delete | `DELETE` | `/discord/admin/role-mappings/{id}` |
| Guild roles (picker) | `GET` | `/discord/admin/discord-roles` |

Request body for create/update:

```json
{
  "spatie_role_name": "member",
  "discord_role_id": "1234567890123456789",
  "sync_to_discord": true,
  "sync_from_discord": false
}
```

Populate Spatie role names from your existing roles admin; use `discord-roles` to label Discord roles in the UI.

## 3. ACP — sync status and manual sync

| Action | Method | Path |
|--------|--------|------|
| Summary | `GET` | `/discord/admin/sync/status` |
| Run history | `GET` | `/discord/admin/sync/runs?per_page=15` |
| Sync one user | `POST` | `/discord/admin/sync/users/{userId}` |

Optional query/body for manual sync:

- Default: queues `SyncUserDiscordRolesJob`
- `?sync=1` — run immediately via `RoleSyncService`
- `?dry_run=1` — compute only (with `sync=1`)

Show `stale_linked_users` from status alongside the latest `discord_sync_runs` row.

## 4. Scheduled stale sync

The package registers an hourly `discord:sync-roles --stale` job when `DISCORD_SCHEDULE_STALE_SYNC=true` (default). Ensure MetaForge runs the scheduler (`php artisan schedule:work` or cron → `schedule:run`).

Tune window with `DISCORD_STALE_SYNC_HOURS` (default `24`).

## 5. User settings — Discord notifications

`GET /discord/user/notification-preferences` returns:

```json
{
  "categories": {
    "support_ticket_reply": "Support ticket replies",
    "order_shipped": "Order shipped updates",
    "account_moderation": "Account moderation notices"
  },
  "preferences": {
    "support_ticket_reply": false,
    "order_shipped": false,
    "account_moderation": false
  }
}
```

`PUT` the same shape with `preferences` object (booleans). Opt-in is **off** until the user enables a category.

## 6. Sending notifications with opt-in

Implement `MetaForge\Discord\Contracts\DiscordNotifiableNotification` on notification classes that should respect user preferences:

```php
use MetaForge\Discord\Contracts\DiscordNotifiableNotification;
use MetaForge\Discord\Notifications\Channels\DiscordWebhookChannel;

class SupportTicketReplyNotification extends Notification implements DiscordNotifiableNotification
{
    public function via(object $notifiable): array
    {
        return [DiscordWebhookChannel::class];
    }

    public function discordNotificationCategory(): string
    {
        return 'support_ticket_reply';
    }

    public function toDiscord(object $notifiable): ?array
    {
        // ...
    }
}
```

Notifications without the interface behave as in phase 1 (no preference gate).

Listen for `MetaForge\Discord\Events\UserDiscordNotificationPreferenceUpdated` if you need audit logs or analytics.

## 7. Bot slash commands

No laravel-vue code required. Ensure production `.env` has `DISCORD_BOT_SHARED_SECRET` and the bot `.env` uses the same value for `METAFORGE_WEBHOOK_SECRET`. Set `METAFORGE_WEBHOOK_URL` to the app origin (e.g. `https://metaforge.example.com`); the bot calls signed `POST` routes under `/discord/bot/members/*`.

Restart the bot after deploy so `/link` and `/roles` register in the guild.
