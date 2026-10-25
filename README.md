# MetaForge Discord Bot

Discord companion for [MetaForge (laravel-vue)](https://github.com/MetaGrenade/laravel-vue): **role sync** (Spatie → Discord), Laravel **notifications** via webhooks, and a **gateway bot** for slash commands and guild events.

## Packages

| Directory | Description |
|-----------|-------------|
| [`laravel/`](laravel/) | Installable Laravel package (`metagrenade/metaforge-discord`) |
| [`bot/`](bot/) | TypeScript [discord.js](https://discord.js.org/) gateway process |

Project architecture and roadmap: see the MetaForge Project store document `docs/discord-bot-plan.md`.

## Quick start

### 1. Install the Laravel package into MetaForge

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/MetaGrenade/MetaForge-DiscordBot"
    }
  ],
  "require": {
    "metagrenade/metaforge-discord": "dev-main"
  }
}
```

Or during local development, use a path repository pointing at `laravel/`.

```bash
php artisan vendor:publish --tag=metaforge-discord-config
php artisan migrate
php artisan discord:ping
php artisan discord:sync-roles --dry-run
```

### 2. Configure environment (MetaForge)

```env
DISCORD_BOT_TOKEN=
DISCORD_GUILD_ID=
DISCORD_APPLICATION_ID=
DISCORD_BOT_SHARED_SECRET=
DISCORD_WEBHOOK_URL=
DISCORD_ROLE_SYNC_ENABLED=true
```

Users must link Discord via MetaForge’s existing OAuth (`social_accounts` with `provider = discord`).

Role mappings can be seeded manually or managed via the **ACP API** (see [laravel-vue integration](docs/laravel-vue-integration.md)).

### 3. Run the bot

```bash
cd bot
cp .env.example .env
npm install
npm run dev
```

Set `METAFORGE_WEBHOOK_URL` to your app base URL (the package exposes `POST /discord/bot/events`).

### 4. Operator tools (phase 2)

- **Admin JSON API** (`/discord/admin/*`): role mapping CRUD, sync run history, manual per-user sync, guild role list for pickers.
- **Stale sync**: `php artisan discord:sync-roles --stale` (optional `--stale-hours=`); scheduled hourly when `DISCORD_SCHEDULE_STALE_SYNC=true`.
- **Slash commands**: `/link` (OAuth link status), `/roles` (read-only Spatie + mapped Discord roles).
- **User notification opt-in**: `/discord/user/notification-preferences` + `DiscordNotifiableNotification` on notification classes.

MetaForge ACP wiring steps: [docs/laravel-vue-integration.md](docs/laravel-vue-integration.md).

### 5. Notifications

Register the channel in MetaForge and implement `toDiscord()` on notifications:

```php
use MetaForge\Discord\Notifications\Channels\DiscordWebhookChannel;

// In a notification class:
public function via(object $notifiable): array
{
    return [DiscordWebhookChannel::class];
}

public function toDiscord(object $notifiable): array
{
    return [
        'content' => 'Hello from MetaForge',
        'embeds' => [['title' => 'Update', 'description' => '…']],
    ];
}
```

Optional env (phase 2):

```env
DISCORD_STALE_SYNC_HOURS=24
DISCORD_SCHEDULE_STALE_SYNC=true
DISCORD_ADMIN_ROUTE_PREFIX=discord/admin
DISCORD_USER_ROUTE_PREFIX=discord/user
```

## Development (this repository)

```bash
cd laravel && composer install && composer test
cd ../bot && npm install && npm run typecheck
```

## License

MIT — see [LICENSE](LICENSE).
