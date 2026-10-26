# Support ticket hooks (thin layer)

Phase 4 will mirror MetaForge support threads into Discord (and optionally vice versa). This repo ships a **minimal hook** so operators can wire events early without laravel-vue UI changes.

## Bot event (optional, future)

The gateway may forward:

| Event type | When | Payload (sketch) |
|------------|------|------------------|
| `support.ticket.thread_created` | Staff creates a Discord thread for a ticket (future) | `ticket_id`, `thread_id`, `guild_id` |

Today the Laravel `BotEventController` **logs** this type only; no mirroring logic runs.

## MetaForge app events (Laravel package)

Configure fully-qualified event class names in MetaForge `.env`:

```env
DISCORD_SUPPORT_TICKET_EVENT_CLASSES="App\\Events\\SupportTicketCreated,App\\Events\\SupportTicketReplied"
```

When any listed event fires, `QueueSupportTicketDiscordNotification` dispatches `NotifyDiscordSupportTicketJob`, which posts a one-line notice to `DISCORD_WEBHOOK_URL` if set. This is a **stub** for staff visibility until thread mirroring is implemented.

Expected event shape (flexible):

- `ticketId` / `ticket_id` / `id`, or `ticket()` returning an object with `id`
- Optional `userId` / `user_id`

## Next steps (phase 4)

- Map ticket ↔ thread IDs in a dedicated table.
- Bot posts/replies in threads; Laravel validates HMAC on inbound ticket updates.
- User opt-in via existing `DiscordNotifiableNotification` categories.

See [security.md](./security.md) for trust boundaries.
