<?php

namespace MetaForge\Discord\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MetaForge\Discord\Models\DiscordSyncFailure;
use MetaForge\Discord\Support\DiscordObservability;
use MetaForge\Discord\Support\DiscordStructuredLog;
use MetaForge\Discord\Services\DiscordInboundRoleSyncService;

class ApplyDiscordInboundRolesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  list<string>  $discordRoleIds
     */
    public function __construct(
        public string $discordUserId,
        public array $discordRoleIds,
        public string $guildId,
    ) {}

    public function handle(DiscordInboundRoleSyncService $sync): void
    {
        $result = $sync->applyMemberRoles($this->discordUserId, $this->discordRoleIds, $this->guildId);

        if ($result->status === 'failed') {
            DiscordStructuredLog::warning('discord.role_sync.inbound.failed', [
                'discord_user_id' => $this->discordUserId,
                'guild_id' => $this->guildId,
                'message' => $result->message,
            ]);
            DiscordObservability::recordCounter('discord.role_sync.inbound.failed', [
                'discord_user_id' => $this->discordUserId,
            ]);

            DiscordSyncFailure::record(
                DiscordSyncFailure::OPERATION_INBOUND_ROLE_SYNC,
                (string) ($result->message ?? 'Inbound role sync failed.'),
                null,
                $this->discordUserId,
                $this->guildId,
                [
                    'discord_user_id' => $this->discordUserId,
                    'guild_id' => $this->guildId,
                    'discord_role_ids' => $this->discordRoleIds,
                ],
            );
        } elseif ($result->status === 'synced') {
            DiscordStructuredLog::info('discord.role_sync.inbound.synced', [
                'discord_user_id' => $this->discordUserId,
                'guild_id' => $this->guildId,
                'roles_attached' => $result->rolesAttached,
                'roles_detached' => $result->rolesDetached,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        DiscordSyncFailure::record(
            DiscordSyncFailure::OPERATION_INBOUND_ROLE_SYNC,
            $exception->getMessage(),
            null,
            $this->discordUserId,
            $this->guildId,
            [
                'discord_user_id' => $this->discordUserId,
                'guild_id' => $this->guildId,
                'discord_role_ids' => $this->discordRoleIds,
            ],
        );

        DiscordStructuredLog::warning('discord.role_sync.inbound.job_failed', [
            'discord_user_id' => $this->discordUserId,
            'guild_id' => $this->guildId,
            'error' => $exception->getMessage(),
        ]);
    }
}
