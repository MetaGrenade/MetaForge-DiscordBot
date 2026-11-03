# Moderation ↔ Discord

Two channels serve different audiences:

| Path | Audience | Purpose |
|------|----------|---------|
| **Staff mirror** | Moderators in a private Discord channel | Action metadata + ACP deep link when a warn/ban/unban/removal is recorded |
| **User notice** | The affected member (opt-in) | `WarningIssued` / ban notices via `DiscordNotifiableNotification` + category `account_moderation` |

Staff mirroring and user DMs are independent. Ticket-style staff embeds never replace email for legally sensitive account notices unless your policy says otherwise.

All outbound Discord posts use the **queue** with `discord-outbound` rate limiting.

## Configuration (MetaForge `.env`)

```env
DISCORD_MODERATION_MIRROR_ENABLED=true

# Staff channel (bot token) or webhook-only:
DISCORD_MODERATION_CHANNEL_ID=123456789012345678
# DISCORD_MODERATION_WEBHOOK_URL=https://discord.com/api/webhooks/...

# Deep link for embeds (ACP user moderation):
DISCORD_MODERATION_URL_TEMPLATE="${APP_URL}/acp/users/{user_id}/moderation"

# Optional: include truncated public reason in staff embeds (default off — see Security)
# DISCORD_MODERATION_STAFF_REASON_IN_EMBED=false
# DISCORD_MODERATION_REASON_EXCERPT_MAX=200
```

Register host events (comma-separated FQCNs):

```env
DISCORD_MODERATION_EVENT_CLASSES=App\\Events\\ModerationDiscordMirror
```

## Wiring MetaForge events

### Option A — domain events (recommended)

After each successful `ModerationAction` write in `App\Support\Moderation\Moderation`, dispatch a mirror event (laravel-vue follow-up PR). The companion listens when the class is listed in `DISCORD_MODERATION_EVENT_CLASSES`.

Suggested host event:

```php
namespace App\Events;

use App\Models\ModerationAction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModerationDiscordMirror
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public ModerationAction $action)
    {
        $this->action->loadMissing(['member', 'actor']);
    }

    public function moderationAction(): ModerationAction
    {
        return $this->action;
    }

    public function moderationActionUrl(): string
    {
        return route('acp.users.moderation', $this->action->user_id);
    }
}
```

The package extractor reads `moderationAction()` (or `moderation_action` property) and maps `ModerationAction` fields: `id`, `type`, `user_id`, `actor_id`, optional `subject_label`, optional `reason` (only when `DISCORD_MODERATION_STAFF_REASON_IN_EMBED=true`).

Supported `type` values: `warning`, `ban`, `unban`, `removal` (matches laravel-vue `ModerationAction` constants).

### Option B — signed bot webhook (no host events)

`POST /discord/bot/moderation` with the same HMAC headers as other bot routes ([security.md](./security.md)):

```json
{
  "action_id": 12,
  "user_id": 34,
  "type": "warning",
  "actor_id": 1,
  "subject_label": "Forum thread title (optional)",
  "url": "https://your-app.test/acp/users/34/moderation"
}
```

Do not send `note`, email, nickname, or full `reason` unless you intentionally enable staff reason excerpts via env and pass a pre-truncated `reason_excerpt`.

## User notifications (opt-in)

laravel-vue today sends `WarningIssued` (and ban/unban mail) on the **mail** channel only. To add Discord for members who opt in:

1. Implement `MetaForge\Discord\Contracts\DiscordNotifiableNotification` on the notification class.
2. Return `account_moderation` from `discordNotificationCategory()`.
3. Add `DiscordWebhookChannel::class` to `via()` **in addition to** mail when Discord should be attempted.
4. Implement `toDiscord()` with **no PII in embed text** beyond what the member already sees in account standing (e.g. their own warning reason — not staff `note`).

Example shape (host app — not shipped in this package):

```php
use MetaForge\Discord\Contracts\DiscordNotifiableNotification;
use MetaForge\Discord\Notifications\Channels\DiscordWebhookChannel;

class WarningIssued extends Notification implements ShouldQueue, DiscordNotifiableNotification
{
    public function via(object $notifiable): array
    {
        return ['mail', DiscordWebhookChannel::class];
    }

    public function discordNotificationCategory(): string
    {
        return 'account_moderation';
    }

    public function toDiscord(object $notifiable): ?array
    {
        // Prefer bot DM to linked Discord user (social account) when available;
        // otherwise return null so mail remains the source of truth.
        return [
            'embeds' => [[
                'title' => 'Account warning',
                'description' => 'You received a warning on your MetaForge account.',
                'fields' => [
                    ['name' => 'Reason', 'value' => Str::limit($this->reason, 200)],
                ],
            ]],
            'allowed_mentions' => ['parse' => []],
        ];
    }
}
```

User preferences: `GET/PUT /discord/user/notification-preferences` — category key `account_moderation` is already defined in package config.

## Security

- **Staff embeds (default):** numeric `action_id`, `type`, member `user_id`, actor `user_id` only. No email, nickname, IP, staff `note`, or full `reason`.
- **Subject labels:** optional truncated forum/thread titles; omit if labels might contain private content.
- **Logs:** action id, type, and user ids — not reason or note bodies.
- **Ingest route:** `discord.bot` HMAC middleware; fail closed without `DISCORD_BOT_SHARED_SECRET`.
- **Removal actions:** mirrored for staff visibility; members are not notified in-app for removals — do not leak removal detail into public Discord channels.

## laravel-vue follow-up (separate PR)

1. Add `App\Events\ModerationDiscordMirror` and `event(new ModerationDiscordMirror($action))` at the end of `warn`, `ban`, `unban`, and removal paths in `App\Support\Moderation\Moderation` (skip when mirror disabled via config if desired).
2. Set `DISCORD_MODERATION_EVENT_CLASSES` and moderation channel env in `.env.example`.
3. Optionally extend `WarningIssued`, `AccountBanned`, and `BanLifted` with `DiscordNotifiableNotification` as above.
4. Confirm ACP route name for `DISCORD_MODERATION_URL_TEMPLATE` (`acp.users.moderation` or equivalent).

Package reference: `ModerationEventExtractor`, `NotifyDiscordModerationJob`, `ModerationMirrorService`.
