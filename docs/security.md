# MetaForge Discord — security model

This document describes trust boundaries, authentication, and operational hardening for the **MetaForge-DiscordBot** companion (Laravel package + gateway bot).

## Trust boundaries

| Actor | Capability | Trust |
|-------|------------|-------|
| Internet | No direct access to bot token or shared secret | Untrusted |
| Discord gateway | Delivers guild/member events to the bot process | Semi-trusted (verify snowflakes + guild ID) |
| Gateway bot | Forwards signed events to MetaForge | Trusted if HMAC + timestamp valid |
| MetaForge app | Spatie RBAC, queues, Discord REST | System of record |
| Operators | ACP, artisan, env secrets | Trusted with least privilege |

## Bot → Laravel (webhooks)

All routes under `/discord/bot/*` use middleware `discord.bot` (`VerifyDiscordBotRequest`):

- **Fail closed** when `DISCORD_BOT_SHARED_SECRET` is empty (HTTP 503).
- Requires `X-Discord-Bot-Timestamp` and `X-Discord-Bot-Signature`.
- Rejects replays outside `DISCORD_WEBHOOK_MAX_SKEW` (default 300s).
- Signature: `HMAC-SHA256(timestamp + '.' + raw_body, secret)` compared with `hash_equals`.

The bot must never log the shared secret or bot token. Use env vars only.

## Laravel → Discord (REST)

- Bot token in `Authorization: Bot …` via `DiscordApiClient` only (never exposed to browsers).
- Outbound sync runs on queues with **rate limiting** (`discord-api` limiter) and HTTP retries honoring **429 Retry-After** where available.
- `SyncUserDiscordRolesJob` is **unique per user** and uses **WithoutOverlapping** to dedupe concurrent syncs.

## Admin and user HTTP APIs

Package routes:

- `/discord/admin/*` — role mappings, sync control, guild role picker.
- `/discord/user/*` — notification preferences.

Defaults ship with `api` middleware only so local Testbench works. **Production MetaForge hosts must add authentication and authorization** (e.g. Sanctum + `can:manage-settings`) via `config/discord.php`:

- `admin_middleware`
- `user_middleware`

Until the host adds guards, those endpoints are **not** safe on the public internet.

## Role sync and privilege escalation

| Direction | Control |
|-----------|---------|
| App → Discord | Only roles listed in `discord_role_mappings` with `sync_to_discord=true`. |
| Discord → app | Only mappings with `sync_from_discord=true`; gated by `DISCORD_SYNC_FROM_DISCORD_ENABLED`. |
| Conflict | `DISCORD_INBOUND_CONFLICT_POLICY`: `discord_wins` (apply Discord) or `app_wins` (ignore inbound). |

Inbound updates suppress outbound sync briefly (`OutboundDiscordRoleSyncGuard`) to prevent Spatie ↔ Discord feedback loops.

Operators should map **least-privilege** Discord roles and keep the bot role **below** managed roles in the guild hierarchy.

## Support ticket stub

Optional webhook event `support.ticket.thread_created` is logged only today. Host ticket events wired through `DISCORD_SUPPORT_TICKET_EVENT_CLASSES` queue a webhook stub — no ticket content is mirrored until phase 4.

## Secrets checklist

- Rotate `DISCORD_BOT_SHARED_SECRET` if leaked; update bot `METAFORGE_WEBHOOK_SECRET` in the same change window.
- Never commit `.env`, tokens, or webhook URLs.
- Restrict queue workers and `.env` to infrastructure roles.

## Related

- [support-tickets.md](./support-tickets.md) — future thread mirroring
- Package middleware: `laravel/src/Http/Middleware/VerifyDiscordBotRequest.php`
