<?php

namespace MetaForge\Discord\Services;

use MetaForge\Discord\Jobs\ApplyDiscordInboundRolesJob;
use MetaForge\Discord\Jobs\SyncUserDiscordRolesJob;
use MetaForge\Discord\Models\DiscordSyncFailure;

class DiscordSyncFailureService
{
    /**
     * @return array{retried: int, skipped: int}
     */
    public function retryUnresolved(int $limit = 50, bool $dryRun = false): array
    {
        $counts = ['retried' => 0, 'skipped' => 0];

        $failures = DiscordSyncFailure::query()
            ->whereNull('resolved_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        foreach ($failures as $failure) {
            if (! $this->canRetry($failure)) {
                $counts['skipped']++;

                continue;
            }

            if ($dryRun) {
                $counts['retried']++;

                continue;
            }

            $this->dispatchRetry($failure);
            $failure->markResolved();
            $counts['retried']++;
        }

        return $counts;
    }

    private function canRetry(DiscordSyncFailure $failure): bool
    {
        return in_array($failure->operation, [
            DiscordSyncFailure::OPERATION_OUTBOUND_ROLE_SYNC,
            DiscordSyncFailure::OPERATION_INBOUND_ROLE_SYNC,
        ], true);
    }

    private function dispatchRetry(DiscordSyncFailure $failure): void
    {
        $payload = $failure->payload ?? [];

        if ($failure->operation === DiscordSyncFailure::OPERATION_OUTBOUND_ROLE_SYNC) {
            $userId = $failure->user_id ?? ($payload['user_id'] ?? null);
            $userClass = $payload['user_class'] ?? config('discord.user_model');

            if (! is_int($userId) && ! is_numeric($userId)) {
                return;
            }

            dispatch(new SyncUserDiscordRolesJob((int) $userId, (string) $userClass));

            return;
        }

        if ($failure->operation === DiscordSyncFailure::OPERATION_INBOUND_ROLE_SYNC) {
            $discordUserId = $failure->discord_user_id ?? ($payload['discord_user_id'] ?? null);
            $guildId = $failure->guild_id ?? ($payload['guild_id'] ?? null);
            /** @var list<string>|null $roles */
            $roles = $payload['discord_role_ids'] ?? null;

            if (! is_string($discordUserId) || ! is_string($guildId) || ! is_array($roles)) {
                return;
            }

            dispatch(new ApplyDiscordInboundRolesJob($discordUserId, $roles, $guildId));
        }
    }
}
