<?php

namespace MetaForge\Discord\Services;

use Illuminate\Database\Eloquent\Model;
use MetaForge\Discord\Models\DiscordRoleMapping;
use MetaForge\Discord\Models\DiscordSyncRun;
use MetaForge\Discord\Support\OutboundDiscordRoleSyncGuard;

class DiscordInboundRoleSyncResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $message = null,
        public readonly int $rolesAttached = 0,
        public readonly int $rolesDetached = 0,
    ) {}
}

class DiscordInboundRoleSyncService
{
    public function __construct(
        private readonly DiscordMemberLookupService $members,
    ) {}

    /**
     * @param  list<string>  $discordRoleIds
     */
    public function applyMemberRoles(string $discordUserId, array $discordRoleIds, string $guildId): DiscordInboundRoleSyncResult
    {
        if (! config('discord.sync_from_discord_enabled', true)) {
            return new DiscordInboundRoleSyncResult('skipped', 'Discord → app role sync is disabled.');
        }

        $configuredGuild = (string) config('discord.guild_id');
        if ($configuredGuild !== '' && $guildId !== $configuredGuild) {
            return new DiscordInboundRoleSyncResult('skipped', 'Event guild does not match DISCORD_GUILD_ID.');
        }

        if ((string) config('discord.inbound_conflict_policy', 'discord_wins') === 'app_wins') {
            return new DiscordInboundRoleSyncResult('skipped', 'Conflict policy is app_wins — ignoring Discord role changes.');
        }

        $user = $this->findUserByDiscordId($discordUserId);
        if ($user === null) {
            return new DiscordInboundRoleSyncResult('skipped', 'Discord member is not linked to a MetaForge user.');
        }

        if (! method_exists($user, 'assignRole') || ! method_exists($user, 'removeRole')) {
            return new DiscordInboundRoleSyncResult('skipped', 'User model does not support Spatie roles.');
        }

        $mappings = DiscordRoleMapping::query()
            ->where('sync_from_discord', true)
            ->get();

        if ($mappings->isEmpty()) {
            return new DiscordInboundRoleSyncResult('skipped', 'No role mappings with sync_from_discord enabled.');
        }

        $discordRoleSet = array_fill_keys($discordRoleIds, true);
        $attached = 0;
        $detached = 0;

        OutboundDiscordRoleSyncGuard::suppress((int) $user->getKey());

        try {
            foreach ($mappings as $mapping) {
                $hasDiscordRole = isset($discordRoleSet[$mapping->discord_role_id]);
                $hasSpatieRole = $user->hasRole($mapping->spatie_role_name);

                if ($hasDiscordRole && ! $hasSpatieRole) {
                    $user->assignRole($mapping->spatie_role_name);
                    $attached++;
                } elseif (! $hasDiscordRole && $hasSpatieRole) {
                    $user->removeRole($mapping->spatie_role_name);
                    $detached++;
                }
            }
        } finally {
            OutboundDiscordRoleSyncGuard::clear((int) $user->getKey());
        }

        DiscordSyncRun::query()->create([
            'trigger' => DiscordSyncRun::TRIGGER_BOT,
            'status' => DiscordSyncRun::STATUS_OK,
            'users_processed' => 1,
            'users_synced' => ($attached + $detached) > 0 ? 1 : 0,
            'meta' => [
                'event' => 'guild.member.update',
                'discord_user_id' => $discordUserId,
                'roles_attached' => $attached,
                'roles_detached' => $detached,
            ],
            'finished_at' => now(),
        ]);

        return new DiscordInboundRoleSyncResult('synced', null, $attached, $detached);
    }

    private function findUserByDiscordId(string $discordUserId): ?Model
    {
        $status = $this->members->linkStatus($discordUserId);

        if ($status['linked'] !== true) {
            return null;
        }

        $userClass = config('discord.user_model');

        return $userClass::query()->find($status['user']['id'] ?? 0);
    }
}
