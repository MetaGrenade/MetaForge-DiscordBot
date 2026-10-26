# Forum and announcement cross-post

Cross-post **new forum threads** (or RSS feed items) from MetaForge to a Discord channel. Outbound posts are **queued** and rate-limited (`discord-outbound`).

## Event-driven (recommended)

MetaForge dispatches `App\Events\ForumPostCreated` when a post is published. Enable cross-post in `.env`:

```env
DISCORD_FORUM_CROSSPOST_ENABLED=true
DISCORD_FORUM_CROSSPOST_CHANNEL_ID=123456789012345678
# or webhook-only (no thread-per-item):
# DISCORD_FORUM_CROSSPOST_WEBHOOK_URL=https://discord.com/api/webhooks/...

DISCORD_FORUM_CROSSPOST_EVENT_CLASSES=App\\Events\\ForumPostCreated
DISCORD_FORUM_CROSSPOST_MODE=new_threads
# Optional board filter (comma slugs):
# DISCORD_FORUM_CROSSPOST_BOARD_SLUGS=announcements,news
```

When `DISCORD_FORUM_CROSSPOST_MODE=new_threads`, only the **first post** on a thread is mirrored (replies are skipped).

If your host app does not expose canonical thread URLs on the event, set:

```env
DISCORD_FORUM_CROSSPOST_URL_TEMPLATE="${APP_URL}/forum/{board}/{thread}"
```

## RSS / Atom poll (optional)

For feeds already exposed by MetaForge (`ForumFeedController`, see laravel-vue `docs/forum-rss.md`):

```env
DISCORD_FORUM_RSS_URL="${APP_URL}/forum/feed.rss"
```

Schedule:

```bash
php artisan discord:forum-rss-sync --limit=10
```

Dedupe uses table `discord_forum_crosspost_log` (`source_key` = feed guid/id).

## Bot dependency

Cross-post uses the **Laravel queue + Discord bot token** (`DISCORD_BOT_TOKEN`) when posting to a channel ID. The gateway bot process does not need changes for RSS/event mode.

## Security

- Embeds contain public forum title/excerpt/URL only — no author email.
- Disable with `DISCORD_FORUM_CROSSPOST_ENABLED=false` on staging.

See [security.md](./security.md).
