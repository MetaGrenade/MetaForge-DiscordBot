# MetaForge Discord companion — end-to-end setup

Ordered checklist to connect [MetaForge (laravel-vue)](https://github.com/MetaGrenade/laravel-vue) with the [MetaForge-DiscordBot](https://github.com/MetaGrenade/MetaForge-DiscordBot) companion (Laravel package + gateway bot). **Caleb must supply all Discord and production secrets** — nothing in this doc contains real credentials.

**Related docs**

- Architecture and roadmap: [discord-bot-plan.md](./discord-bot-plan.md)
- ACP UI, middleware, and notification wiring in laravel-vue: [laravel-vue-integration.md](https://github.com/MetaGrenade/MetaForge-DiscordBot/blob/main/docs/laravel-vue-integration.md) (companion repo)

---

## Prerequisites

- [ ] MetaForge laravel-vue with PR **#309** merged (or equivalent): `packages/metaforge-discord`, ACP Discord pages, and `.env.example` companion keys.
- [ ] PHP **8.4+**, Composer, Node **20+**, and MetaForge’s usual **queue worker** and **scheduler** (same as production MetaForge).
- [ ] A Discord account with permission to create applications and manage the target server.

---

## 1. Discord Developer Portal

Use one application for **OAuth login** and the **bot** (recommended), or separate apps if you prefer — phase 1 assumes a single guild.

### 1.1 Create the application

- [ ] Open [Discord Developer Portal](https://discord.com/developers/applications) → **New Application** (e.g. “MetaForge Companion”).
- [ ] Note **Application ID** → maps to `DISCORD_APPLICATION_ID` (and often equals OAuth **Client ID**).

### 1.2 OAuth2 (MetaForge login — existing flow)

- [ ] **OAuth2 → Redirects**: add `{APP_URL}/auth/oauth/discord/callback` (match MetaForge `DISCORD_REDIRECT_URI`).
- [ ] **OAuth2 → URL Generator** (user login): scopes `identify`, `email` — used by MetaForge’s Discord OAuth provider, not the bot install link.
- [ ] Copy **Client ID** and **Client Secret** → MetaForge `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`.

### 1.3 Bot user

- [ ] **Bot** tab → **Add Bot** → reset/copy **Bot Token** → MetaForge `DISCORD_BOT_TOKEN` and bot process `DISCORD_BOT_TOKEN` (same token).
- [ ] **Privileged Gateway Intents**: enable **Server Members Intent** (required for member events and role-aware flows). **Message Content Intent** only if you add moderation/content features later.
- [ ] Under **Bot → Permissions**, ensure the bot can **Manage Roles** (role sync). It must sit **below** the roles it assigns in the server role list.

### 1.4 Install bot + slash commands

- [ ] **OAuth2 → URL Generator**: scopes **`bot`** + **`applications.commands`**.
- [ ] Bot permissions (minimum): **Manage Roles**, **Send Messages**, **Use Slash Commands**, **View Channels** (expand later for moderation).
- [ ] Open the generated URL, select your **guild**, authorize.
- [ ] Copy **Server ID** (Developer Mode: User Settings → App Settings → Advanced → Developer Mode, then right‑click server → Copy Server ID) → `DISCORD_GUILD_ID`.

---

## 2. Shared secret (bot ↔ Laravel)

- [ ] Generate a random string (≥ 32 bytes), e.g. `openssl rand -hex 32`.
- [ ] MetaForge `.env`: `DISCORD_BOT_SHARED_SECRET=<value>`
- [ ] Bot `bot/.env`: `METAFORGE_WEBHOOK_SECRET=<same value>`

---

## 3. MetaForge (laravel-vue) environment

Add or confirm in MetaForge `.env` (see also `.env.example` after #309):

```env
# Existing Discord OAuth
DISCORD_CLIENT_ID=
DISCORD_CLIENT_SECRET=
DISCORD_REDIRECT_URI="${APP_URL}/auth/oauth/discord/callback"

# Companion
DISCORD_BOT_TOKEN=
DISCORD_GUILD_ID=
DISCORD_APPLICATION_ID=
DISCORD_BOT_SHARED_SECRET=
DISCORD_WEBHOOK_URL=              # optional default channel webhook for notifications
DISCORD_ROLE_SYNC_ENABLED=true
DISCORD_SCHEDULE_STALE_SYNC=true
DISCORD_API_BASE=https://discord.com/api/v10
```

- [ ] `APP_URL` must be the URL the **bot** and **Discord** can reach in each environment (use HTTPS + public URL in production for slash commands and webhooks).

Optional published config (`config/discord.php`): set `admin_middleware` / `user_middleware` to MetaForge `web`, `auth`, and ACP gates — see [laravel-vue-integration.md](https://github.com/MetaGrenade/MetaForge-DiscordBot/blob/main/docs/laravel-vue-integration.md).

---

## 4. Laravel package install

### 4.1 Production / CI (VCS)

MetaForge `composer.json` already points at the companion repo; on the server:

- [ ] `composer install` (or `composer update metagrenade/metaforge-discord` when bumping the companion).

### 4.2 Local dev — clone into `packages/metaforge-discord`

Mirrors MetaForge CI (see companion repo `laravel/`):

```bash
cd /path/to/laravel-vue
rm -rf packages/metaforge-discord
git clone https://github.com/MetaGrenade/MetaForge-DiscordBot.git /tmp/metaforge-discord-bot
mkdir -p packages
cp -R /tmp/metaforge-discord-bot/laravel packages/metaforge-discord
composer update metagrenade/metaforge-discord --no-interaction
```

Or use a Composer **path** repository to `packages/metaforge-discord` during active package development.

### 4.3 Publish config and migrate

```bash
php artisan vendor:publish --tag=metaforge-discord-config   # if not already committed in laravel-vue
php artisan migrate
```

- [ ] Confirm tables: `discord_role_mappings`, `discord_sync_runs`, `discord_user_sync_states`, `discord_notification_preferences`.

---

## 5. Queue worker and scheduler

Role sync and webhooks use Laravel queues when not run synchronously.

- [ ] Run a queue worker (same driver as the rest of MetaForge — database, Redis, etc.): e.g. `php artisan queue:work`.
- [ ] Run the scheduler: cron `* * * * * php artisan schedule:run` or `php artisan schedule:work` locally.
- [ ] With `DISCORD_SCHEDULE_STALE_SYNC=true`, the package registers hourly `discord:sync-roles --stale`.

The **gateway bot does not require Redis**; only MetaForge’s queue configuration matters for jobs.

---

## 6. Verify Laravel ↔ Discord API

From MetaForge project root:

```bash
php artisan discord:ping
```

- [ ] Expect: `Discord bot authenticated as <username> (<id>).`
- [ ] On failure: check `DISCORD_BOT_TOKEN`, token not revoked, and outbound HTTPS to `discord.com`.

Optional dry run:

```bash
php artisan discord:sync-roles --dry-run
```

---

## 7. Gateway bot process

From the [MetaForge-DiscordBot](https://github.com/MetaGrenade/MetaForge-DiscordBot) repo (or your deploy artifact):

```bash
cd bot
cp .env.example .env
# Edit .env — Caleb supplies secrets
npm install
npm run build    # production
npm start        # or npm run dev for local watch
```

**`bot/.env`**

```env
DISCORD_BOT_TOKEN=
DISCORD_GUILD_ID=
DISCORD_APPLICATION_ID=
METAFORGE_WEBHOOK_URL=https://your-metaforge.example.com
METAFORGE_WEBHOOK_SECRET=    # same as DISCORD_BOT_SHARED_SECRET
```

- [ ] `METAFORGE_WEBHOOK_URL` is the **origin only** (no path); the bot posts to `/discord/bot/events` and `/discord/bot/members/*`.
- [ ] Bot logs in and registers guild slash commands on startup (`/ping`, `/link`, `/roles`).

**Production options:** systemd/supervisor on the app VM, or Docker — see companion repo `docker-compose.yml` and [Production setup](https://github.com/MetaGrenade/MetaForge-DiscordBot/blob/main/README.md#production-setup).

Validate bot env before start:

```bash
./scripts/setup-check.sh
# or: make setup-check
```

---

## 8. Smoke test in Discord

- [ ] Bot shows **online** in the guild member list.
- [ ] Run **`/ping`** in Discord → ephemeral reply: “MetaForge Discord bot is online.”
- [ ] Link a test user via MetaForge **Discord OAuth**, then **`/link`** → shows linked account.
- [ ] **`/roles`** → read-only Spatie + mapped Discord role IDs (after mappings exist).

---

## 9. First role mapping (ACP)

After at least one user has `social_accounts.provider = discord`:

- [ ] ACP → **Discord integration** (laravel-vue UI from #309), or call admin API: `GET/POST /discord/admin/role-mappings`.
- [ ] Use **`GET /discord/admin/discord-roles`** to pick Discord role IDs.
- [ ] Create a mapping, e.g. Spatie role `member` → Discord role snowflake, `sync_to_discord: true`.
- [ ] Trigger sync: assign the Spatie role to a linked user, or `POST /discord/admin/sync/users/{userId}` (or queue via default body).

Confirm in Discord that the member receives the mapped role (bot role hierarchy must allow it).

Details: [laravel-vue-integration.md § ACP — role mappings](https://github.com/MetaGrenade/MetaForge-DiscordBot/blob/main/docs/laravel-vue-integration.md#2-acp--role-mappings).

---

## 10. Restart bot after deploy (slash commands)

- [ ] After changing `DISCORD_APPLICATION_ID`, guild, or command handlers, **restart the bot process** so guild commands re-register.
- [ ] Restart after production deploys that affect `/link` or `/roles` behavior.

---

## Troubleshooting (quick)

| Symptom | Check |
|--------|--------|
| `discord:ping` fails | Bot token, revoked token, firewall to Discord API |
| Roles not updating | `DISCORD_ROLE_SYNC_ENABLED`, queue worker running, user linked via OAuth, mapping exists, bot role order |
| `/link` “Could not reach MetaForge” | `METAFORGE_WEBHOOK_URL`, HTTPS, shared secret match, app reachable from bot host |
| Slash commands missing | `DISCORD_APPLICATION_ID`, `DISCORD_GUILD_ID`, bot restarted, `applications.commands` scope on invite |
| 401 on bot → Laravel routes | `DISCORD_BOT_SHARED_SECRET` ↔ `METAFORGE_WEBHOOK_SECRET`, clock skew |

For design decisions and phases, see [discord-bot-plan.md](./discord-bot-plan.md).
