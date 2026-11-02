# MetaForge Discord Companion — Architecture & Roadmap

Companion bot and Laravel integration for [MetaForge (laravel-vue)](https://github.com/MetaGrenade/laravel-vue): account linking (via existing Discord OAuth), bidirectional-aware **role sync** (app → Discord in phase 1), Laravel notifications to Discord, and a gateway bot for slash commands and guild events.

## Repository layout

| Path | Purpose |
|------|---------|
| `laravel/` | Composer package `metagrenade/metaforge-discord` — install into MetaForge via path or VCS repo |
| `bot/` | Node.js (TypeScript) gateway process using **discord.js** v14 |
| `README.md` | Quick start, env vars, wiring into laravel-vue |

MetaForge already ships **Discord OAuth** (`App\Support\OAuth\Providers\DiscordProvider`) and `social_accounts` (one Discord identity per user). The companion reuses `provider = discord` and `provider_id` as the Discord snowflake — no duplicate linking table in phase 1.

## Runtime choices

| Layer | Choice | Rationale |
|-------|--------|-----------|
| App integration | **Laravel package** (PSR-4 `MetaForge\Discord`) | Matches MetaForge’s modular `app/Support/*` style; publishes config, migrations, routes |
| Role mutations | **Discord REST API** with bot token from Laravel queues | Reliable, idempotent; no need to route every role change through the gateway |
| Gateway bot | **discord.js** + TypeScript | Slash commands, member join/leave events, future moderation; long-lived process |
| HTTP to app | Signed webhooks (`DISCORD_BOT_SHARED_SECRET`) | Same pattern as Stripe/Tebex webhooks in MetaForge |
| RBAC source of truth | **Spatie roles on `User`** (phase 1: app → Discord) | Aligns with existing ACP and permissions; Discord → app in phase 3 |

Alternatives considered:

- **DiscordPHP** in PHP: viable for a single-process setup, but MetaForge already runs queues + a separate Vite dev process; a small Node bot matches common Discord ops tooling.
- **Laravel Notification channel only (webhooks)**: good for alerts, insufficient for role sync and slash commands.

## Discord application setup

1. [Discord Developer Portal](https://discord.com/developers/applications) → **New Application** (e.g. “MetaForge Companion”).
2. **OAuth2** (for MetaForge login — unchanged): redirect URI `{APP_URL}/oauth/discord/callback`, scopes `identify`, `email`.
3. **Bot** tab: create bot, enable **Server Members Intent** and **Message Content Intent** (only if moderation commands need content in later phases).
4. **OAuth2 URL Generator** (bot install): scopes `bot`, `applications.commands`; permissions minimally **Manage Roles**, **Send Messages**, **Use Slash Commands**, **View Channels** (expand in phase 4 for moderation).
5. Invite bot to the guild; copy **Guild ID**.
6. Copy **Application ID**, **Bot Token**, **Client ID/Secret** (OAuth uses same app or a dedicated app — one app can serve both if scopes are combined).

## Environment variables

### MetaForge (`.env`)

```env
DISCORD_BOT_TOKEN=                    # Bot token (role sync REST + optional bot features)
DISCORD_GUILD_ID=                     # Target guild snowflake
DISCORD_APPLICATION_ID=               # For slash command registration (phase 2+)
DISCORD_BOT_SHARED_SECRET=            # HMAC shared with bot process; min 32 random bytes
DISCORD_WEBHOOK_URL=                  # Optional: default channel webhook for notifications
DISCORD_ROLE_SYNC_ENABLED=true
DISCORD_API_BASE=https://discord.com/api/v10

# Existing OAuth (unchanged)
DISCORD_CLIENT_ID=
DISCORD_CLIENT_SECRET=
DISCORD_REDIRECT_URI=
```

### Bot process (`bot/.env`)

```env
DISCORD_BOT_TOKEN=
DISCORD_GUILD_ID=
DISCORD_APPLICATION_ID=
METAFORGE_WEBHOOK_URL=https://your-app.test/discord/bot/events
METAFORGE_WEBHOOK_SECRET=             # Same as DISCORD_BOT_SHARED_SECRET
```

## Role sync strategy (phase 1: app → Discord)

1. **Mapping table** `discord_role_mappings`: Spatie role `name` → Discord role snowflake (managed in DB; ACP UI in phase 2).
2. **Resolve member**: `social_accounts` where `provider = discord` → `provider_id`.
3. **Compute desired roles**: union of mapped Discord roles for all Spatie roles on the user; optionally always apply `@everyone` (Discord adds implicitly).
4. **Apply**: `PATCH /guilds/{guild}/members/{user}` with full `roles` array (Discord replaces the set we send — we merge mapped roles with non-managed roles the member already has if `preserve_unmapped_roles` is true in config).
5. **Triggers**: Spatie `RoleAttached` / `RoleDetached` → queued `SyncUserDiscordRolesJob`; artisan `discord:sync-roles` for bulk/backfill; optional schedule (phase 2).
6. **Audit**: `discord_sync_runs` + per-user result stored on job failure logs.

Phase 3 adds **Discord → app** (role change on guild → webhook → update Spatie only for mapped “mirror” roles, with conflict policy documented).

## Notifications

- **Phase 1**: `MetaForge\Discord\Notifications\Channels\DiscordWebhookChannel` — Laravel notifications implement `toDiscord()` and send embeds to `DISCORD_WEBHOOK_URL` or per-notification webhook.
- **Phase 2**: route high-value events (support ticket reply, order shipped, ban) through notification classes; user opt-in via settings.
- **Phase 4**: bot DM for link verification / 2FA backup (optional).

## Security

- Bot token and shared secret **only in env**, never committed.
- Bot → Laravel: `X-Discord-Bot-Signature: sha256=<hmac(body, secret)>`, timestamp header with skew window (replay protection).
- Laravel → Discord: bot token in `Authorization` header via HTTP client; rate-limit aware retries (429 + `Retry-After`).
- Role sync jobs **no-op** when `DISCORD_ROLE_SYNC_ENABLED=false` or user has no linked Discord account.
- Internal routes middleware group; no public role escalation without Spatie change on app side.

## MetaForge installation (after phase 1 merge)

```bash
# In laravel-vue composer.json (path repo during dev)
composer require metagrenade/metaforge-discord:@dev
php artisan vendor:publish --tag=metaforge-discord-config
php artisan migrate
php artisan discord:ping
php artisan discord:sync-roles --dry-run
```

Register service provider is auto-discovered via `composer.json` `extra.laravel.providers`.

## Phased roadmap

### Phase 1 — Scaffold (this PR)

- Laravel package: config, migrations, API client, role sync service, jobs, artisan commands, webhook endpoint, notification channel, Spatie listeners.
- Bot: connect, `/ping`, forward `ready` + `guildMemberAdd` events to Laravel.
- Documentation and `.env.example`.

### Phase 2 — Operator UX

- ACP pages: role mapping CRUD, sync status, manual “sync user”.
- Scheduled `discord:sync-roles --stale`.
- Register slash `/link` (shows link status) and `/roles` (self-service read-only).
- Notification preferences in user settings.

### Phase 3 — Discord → app sync

- Handle `guildMemberUpdate` for mapped roles; policy: Discord wins vs app wins per mapping flag.
- Entitlement/subscription roles tied to Cashier (mirror billing plan roles to Discord).

### Phase 4 — Community features

- Welcome channel messages, support ticket thread mirroring, forum announcement cross-post.
- Moderation helpers (warn/ban sync with MetaForge moderation module).
- Optional voice channel stats / live member count widget for dashboard.

### Phase 5 — Hardening

- Load testing Discord rate limits, dead-letter queue for failed syncs, metrics (OpenTelemetry), multi-guild support.

## Hosting the bot process

The bot must stay connected to the Discord gateway. Options:

- **Sidecar** on the same VM as Laravel (systemd/supervisor) — simplest for small communities.
- **Container** (Fly.io, Railway, ECS) — recommended for production.
- **Serverless** — not suitable for gateway; use only for interactions HTTP if migrating to interactions endpoint later.

Laravel queues must run separately (already standard in MetaForge `composer dev`).

## Decisions needed from Caleb

1. **Single Discord guild** vs multi-guild (phase 1 assumes one `DISCORD_GUILD_ID`).
2. **Same Discord application** for OAuth login and bot, or separate apps.
3. **Which Spatie roles** map to Discord (seed defaults vs empty mappings).
4. **Hosting** for the bot process in production.
5. **Discord → app sync** priority (phase 3) vs notification volume (phase 2).

## References

- [Discord API — Modify Guild Member](https://discord.com/developers/docs/resources/guild#modify-guild-member)
- MetaForge OAuth: `app/Support/OAuth/Providers/DiscordProvider.php`
- MetaForge social accounts: `social_accounts` migration
