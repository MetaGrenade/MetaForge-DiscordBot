# MetaForge Discord Bot

Discord companion for [MetaForge (laravel-vue)](https://github.com/MetaGrenade/laravel-vue): **bidirectional-aware role sync**, Laravel **notifications** via webhooks, and a **gateway bot** for slash commands and guild events.

## Packages

| Directory | Description |
|-----------|-------------|
| [`laravel/`](laravel/) | Installable Laravel package (`metagrenade/metaforge-discord`) |
| [`bot/`](bot/) | TypeScript [discord.js](https://discord.js.org/) gateway process |

Project architecture and roadmap: see the MetaForge Project store document `docs/discord-bot-plan.md`.

End-to-end operator checklist (Developer Portal, secrets, migrations, queue, bot, ACP): **`docs/discord-companion-setup.md`** in the MetaForge Project store.

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

### 4. Discord → app role sync (phase 3)

- Gateway forwards **`guildMemberUpdate`** as `guild.member.update` when mapped Discord roles change.
- Enable per mapping: `sync_from_discord=true` on `discord_role_mappings`.
- Conflict policy: `DISCORD_INBOUND_CONFLICT_POLICY=discord_wins` (default) or `app_wins`.
- Security overview: [docs/security.md](docs/security.md).

```env
DISCORD_SYNC_FROM_DISCORD_ENABLED=true
DISCORD_INBOUND_CONFLICT_POLICY=discord_wins
DISCORD_ROLE_SYNC_CHUNK_SIZE=100
DISCORD_API_RATE_LIMIT_PER_MINUTE=50
```

Bulk outbound sync (`php artisan discord:sync-roles`) **queues** one job per linked user by default; use `--sync` to run in-process (dry-run always in-process).

### 5. Operator tools (phase 2)

- **Admin JSON API** (`/discord/admin/*`): role mapping CRUD, sync run history, manual per-user sync, guild role list for pickers.
- **Stale sync**: `php artisan discord:sync-roles --stale` (optional `--stale-hours=`); scheduled hourly when `DISCORD_SCHEDULE_STALE_SYNC=true`.
- **Slash commands**: `/link` (OAuth link status), `/roles` (read-only Spatie + mapped Discord roles).
- **User notification opt-in**: `/discord/user/notification-preferences` + `DiscordNotifiableNotification` on notification classes.

MetaForge ACP wiring steps: [docs/laravel-vue-integration.md](docs/laravel-vue-integration.md).

### 6. Support ticket hook (stub)

See [docs/support-tickets.md](docs/support-tickets.md) for optional `support.ticket.*` wiring and bot event types.

### 7. Notifications

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

## Production setup

Use this after the Laravel package is installed in MetaForge (laravel-vue PR #309 or equivalent).

1. **Validate environment** (from this repo root):

   ```bash
   cp bot/.env.example bot/.env
   # Fill bot/.env and MetaForge .env — Caleb supplies all Discord secrets
   make setup-check
   ```

2. **MetaForge**: queue worker + scheduler running; `php artisan discord:ping` succeeds; optional `php artisan discord:sync-roles --dry-run`.

3. **Gateway bot** — pick one:

   | Method | Commands |
   |--------|----------|
   | Node (VM) | `make bot-install && make bot-build && make bot-start` |
   | Dev watch | `make bot-dev` |
   | Docker | `docker compose up --build -d` |

4. **Discord**: `/ping` in guild; configure first role mapping in ACP ([laravel-vue integration](docs/laravel-vue-integration.md)); **restart the bot** after deploy so `/link` and `/roles` register.

Bot-only details: [bot/README.md](bot/README.md). The bot does **not** need Redis; MetaForge uses your existing queue driver for sync jobs.

## Development (this repository)

```bash
make test
# or:
cd laravel && composer install && composer test
cd ../bot && npm install && npm run typecheck
```

First-time helper:

```bash
make setup    # npm ci in bot/ + setup-check.sh
```

## License

MIT — see [LICENSE](LICENSE).
