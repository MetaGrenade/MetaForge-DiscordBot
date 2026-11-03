# MetaForger Discord companion — operations

Runbook for production operators after Phase 5 hardening: failed sync recovery, observability, and multi-guild prep.

## Failed sync dead letter

Outbound and inbound role sync jobs record unresolved failures in **`discord_sync_failures`** when:

- A queued job exhausts retries (`failed()` handler), or
- Sync completes with status `failed` (for example, member not in guild).

Each row stores `operation`, optional `user_id` / `discord_user_id`, `guild_id`, JSON `payload` for retry, and `error`.

### Retry

From the MetaForger app root (package installed):

```bash
# Preview
php artisan discord:retry-failures --dry-run

# Re-dispatch up to 50 unresolved failures (default)
php artisan discord:retry-failures

# Custom batch size
php artisan discord:retry-failures --limit=200
```

Successful retries mark rows `resolved_at`. If the job fails again, a **new** failure row is created.

Ensure a **queue worker** is running so retried jobs execute.

### Inspect

```sql
SELECT id, operation, user_id, discord_user_id, guild_id, error, last_failed_at
FROM discord_sync_failures
WHERE resolved_at IS NULL
ORDER BY last_failed_at DESC
LIMIT 20;
```

Pair with per-user state in `discord_user_sync_states` and bulk runs in `discord_sync_runs`.

## Observability

### Structured logging

When `DISCORD_STRUCTURED_LOGGING=true` (default), the package logs events with a `discord_event` key, including:

| Event | Meaning |
|-------|---------|
| `discord.role_sync.outbound.*` | App → Discord role sync |
| `discord.role_sync.inbound.*` | Discord → app role sync |
| `discord.outbound.post` | Bot API or webhook message sent |

Ship these logs to your aggregator (Datadog, CloudWatch, etc.) and filter on `discord_event`.

### Metrics / OpenTelemetry (optional)

No OTEL PHP SDK is required. Set:

```env
DISCORD_METRICS_ENABLED=true
DISCORD_OTEL_EXPORTER_OTLP_ENDPOINT=https://your-collector:4318
DISCORD_OTEL_SERVICE_NAME=metaforge-discord
```

When enabled, counters are emitted as `discord.metric` log lines with `metric`, `delta`, and `attributes`. Bridge them to OTLP in the host app or log pipeline.

## Multi-guild registry

Phase 5 adds **`discord_guilds`** for future multi-server support. Default behavior is unchanged when the table is empty: only `DISCORD_GUILD_ID` is used.

To register guilds explicitly:

```sql
INSERT INTO discord_guilds (guild_id, label, is_primary, enabled, created_at, updated_at)
VALUES ('123456789012345678', 'Main community', true, true, NOW(), NOW());
```

- **Outbound** role sync uses `DISCORD_GUILD_ID`, or the primary enabled row if env is empty.
- **Inbound** events accept any **enabled** registry guild; with an empty registry, inbound still matches `DISCORD_GUILD_ID` only (or all guilds if env is unset — same as before).

Set `DISCORD_GUILD_REGISTRY_ENABLED=true` when you rely on registry rows instead of env-only configuration (documentation flag; behavior is driven by table contents).

## Suggested schedules

| Task | Command | Cadence |
|------|---------|---------|
| Stale role sync | `discord:sync-roles --stale` | Hourly (package default when enabled) |
| Failure retry | `discord:retry-failures` | Every 15–60 minutes (add to host scheduler) |

Example host `routes/console.php` or scheduler:

```php
$schedule->command('discord:retry-failures --limit=100')->everyFifteenMinutes();
```

## Related docs

- Setup checklist: [discord-companion-setup.md](./discord-companion-setup.md)
- Security: [security.md](./security.md)
