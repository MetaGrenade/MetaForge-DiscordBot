# MetaForge Discord gateway bot

Long-lived [discord.js](https://discord.js.org/) v14 process: guild slash commands (`/ping`, `/link`, `/roles`), forwards `ready`, `guildMemberAdd`, and **`guildMemberUpdate`** (role changes) to MetaForge via signed HTTP.

Laravel role sync and notifications run in **MetaForge** (package `metagrenade/metaforge-discord`), not in this folder.

## Requirements

- Node.js **20+**
- Discord **bot token**, **application ID**, and target **guild ID**
- MetaForge app URL and **shared secret** (same as `DISCORD_BOT_SHARED_SECRET`)

End-to-end checklist (Discord Portal, MetaForge `.env`, migrations, ACP): see the MetaForge Project store `docs/discord-companion-setup.md`, or root [README](../README.md#production-setup).

## Configure

```bash
cp .env.example .env
```

| Variable | Description |
|----------|-------------|
| `DISCORD_BOT_TOKEN` | Bot token from Developer Portal → Bot |
| `DISCORD_GUILD_ID` | Target server snowflake |
| `DISCORD_APPLICATION_ID` | Application ID (slash command registration) |
| `METAFORGE_WEBHOOK_URL` | MetaForge **origin** only, e.g. `https://metaforge.example.com` |
| `METAFORGE_WEBHOOK_SECRET` | Same value as MetaForge `DISCORD_BOT_SHARED_SECRET` |

Validate:

```bash
../scripts/setup-check.sh bot
# or from repo root: make setup-check-bot
```

## Run locally

```bash
npm install
npm run dev
```

Production:

```bash
npm run build
npm start
```

## Docker (from repo root)

```bash
cp bot/.env.example bot/.env
# edit bot/.env
docker compose up --build -d
docker compose logs -f bot
```

## Discord Portal reminders

- Enable **Server Members Intent** on the bot.
- Invite URL scopes: `bot` + `applications.commands`; permission **Manage Roles** for sync.
- Bot role must be **above** roles it assigns.

After deploy or command changes, **restart** this process so guild slash commands re-register.

## Endpoints used on MetaForge

| Method | Path |
|--------|------|
| POST | `/discord/bot/events` |
| POST | `/discord/bot/members/link-status` |
| POST | `/discord/bot/members/roles` |

### Bot → Laravel event types

| `type` | Source |
|--------|--------|
| `bot.ready` | Client ready |
| `guild.member.add` | Member joined guild |
| `guild.member.update` | Member roles changed (phase 3 inbound sync) |
| `support.ticket.thread_created` | Reserved for future thread mirroring (logged only) |

All requests use HMAC headers documented in the Laravel package middleware and [docs/security.md](../docs/security.md).
