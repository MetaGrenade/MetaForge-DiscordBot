# Support ticket mirroring

Staff-visible support activity in Discord: ticket opened, reply, status change. User DMs still use `DiscordNotifiableNotification` + opt-in categories in MetaForge.

All outbound Discord posts run on the **queue** with `discord-outbound` rate limiting.

## Configuration (MetaForge `.env`)

```env
DISCORD_SUPPORT_TICKET_MIRROR_ENABLED=true

# Prefer a staff channel + bot token (enables thread-per-ticket):
DISCORD_SUPPORT_TICKET_CHANNEL_ID=123456789012345678
DISCORD_SUPPORT_TICKET_THREAD_PER_TICKET=true

# Or webhook-only (single channel, no threads):
# DISCORD_SUPPORT_TICKET_WEBHOOK_URL=https://discord.com/api/webhooks/...

# Deep link for embeds (ACP or support desk):
DISCORD_SUPPORT_TICKET_URL_TEMPLATE="${APP_URL}/acp/support/tickets/{id}"

# Staff slash command `/ticket` (bot → Laravel):
DISCORD_SUPPORT_STAFF_ROLE_IDS=111,222
# and/or linked MetaForge users with Spatie roles:
DISCORD_SUPPORT_STAFF_SPATIE_ROLES=admin,moderator
```

## Wiring MetaForge events

### Option A — domain events (recommended)

Register event class names (comma-separated):

```env
DISCORD_SUPPORT_TICKET_EVENT_CLASSES=App\\Events\\SupportTicketDiscordMirror
```

laravel-vue can dispatch a single mirror event from `SupportTicketNotificationDispatcher` (see companion PR). Events should expose:

- `ticket()` → model with `id`, `subject`, `status`, `user_id`
- optional `kind`: `opened` | `reply` | `updated` | `closed`
- optional `message()` with `body` for reply excerpts (sanitized, no attachments)

### Option B — signed bot webhook (no host events)

From MetaForge (queued job or listener), `POST` to the package route with the same HMAC headers as the gateway bot:

`POST /discord/bot/support-tickets`

```json
{
  "ticket_id": 42,
  "kind": "reply",
  "subject": "Billing question",
  "status": "open",
  "url": "https://your-app.test/acp/support/tickets/42",
  "excerpt": "Short public-safe excerpt"
}
```

Headers: `X-Discord-Bot-Signature`, `X-Discord-Bot-Timestamp` (see [security.md](./security.md)).

## Thread-per-ticket

When `DISCORD_SUPPORT_TICKET_THREAD_PER_TICKET=true` and a **channel ID** is set:

1. First activity posts a starter embed in the channel.
2. Laravel creates a Discord thread named `ticket-{id}` via the bot token.
3. Later events post **into the thread** (`discord_support_ticket_threads` stores `thread_id`).

Webhook-only mode cannot create threads; use a channel + bot token.

## Staff slash command

The gateway bot registers `/ticket <id>` (guild-only). It calls:

`POST /discord/bot/support-tickets/summary`

with `discord_user_id`, `ticket_id`, and the member's Discord role IDs. Laravel returns a short summary when the caller passes [staff checks](security.md).

Restart the bot after deploy so slash commands refresh.

## User notifications (unchanged)

End-user ticket replies still flow through `TicketReplied` + `DiscordNotifiableNotification` category `support_ticket_reply` — separate from staff channel mirroring.

## Security

- Logs include ticket IDs and event kinds only — not email, full message bodies, or attachment URLs.
- Ingest and summary routes require bot HMAC middleware.
- Tune `DISCORD_OUTBOUND_RATE_LIMIT_PER_MINUTE` (default 30) under load.
