<?php

namespace MetaForge\Discord\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use MetaForge\Discord\Models\DiscordRoleMapping;
use MetaForge\Discord\Models\DiscordSyncRun;

class RoleSyncResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $message = null,
    ) {}
}

class RoleSyncService
{
    public function __construct(
        private readonly DiscordApiClient $client,
    ) {}

    /**
     * @return array{synced: int, skipped: int, failed: int, processed: int}
     */
    public function syncAllUsers(bool $dryRun = false, string $trigger = DiscordSyncRun::TRIGGER_COMMAND): array
    {
        $run = DiscordSyncRun::query()->create([
            'trigger' => $trigger,
            'status' => DiscordSyncRun::STATUS_RUNNING,
        ]);

        $counts = ['synced' => 0, 'skipped' => 0, 'failed' => 0, 'processed' => 0];

        try {
            $userClass = config('discord.user_model');
            /** @var class-string<Model> $userClass */
            $query = $userClass::query()->orderBy('id');

            $query->chunkById(100, function ($users) use (&$counts, $dryRun) {
                foreach ($users as $user) {
                    $counts['processed']++;
                    $result = $this->syncUser($user, $dryRun);

                    match ($result->status) {
                        'synced' => $counts['synced']++,
                        'skipped' => $counts['skipped']++,
                        default => $counts['failed']++,
                    };
                }
            });

            $run->fill([
                'status' => DiscordSyncRun::STATUS_OK,
                'users_processed' => $counts['processed'],
                'users_synced' => $counts['synced'],
                'users_skipped' => $counts['skipped'],
                'users_failed' => $counts['failed'],
                'finished_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            $run->fill([
                'status' => DiscordSyncRun::STATUS_FAILED,
                'error' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();

            throw $exception;
        }

        return $counts;
    }

    public function syncUser(Model $user, bool $dryRun = false): RoleSyncResult
    {
        if (! config('discord.role_sync_enabled')) {
            return new RoleSyncResult('skipped', 'Role sync is disabled.');
        }

        $guildId = (string) config('discord.guild_id');
        if ($guildId === '') {
            return new RoleSyncResult('skipped', 'DISCORD_GUILD_ID is not configured.');
        }

        $discordUserId = $this->resolveDiscordUserId($user);
        if ($discordUserId === null) {
            return new RoleSyncResult('skipped', 'User has no linked Discord account.');
        }

        $desiredRoles = $this->desiredDiscordRoleIds($user);
        if ($desiredRoles->isEmpty()) {
            return new RoleSyncResult('skipped', 'No mapped Spatie roles for this user.');
        }

        if ($dryRun) {
            return new RoleSyncResult('synced', 'Dry run — would apply '.$desiredRoles->count().' role(s).');
        }

        $member = $this->client->getGuildMember($guildId, $discordUserId);
        if ($member === null) {
            return new RoleSyncResult('failed', 'User is not a member of the configured guild.');
        }

        $finalRoles = $this->mergeRoles($member, $desiredRoles);

        $this->client->modifyGuildMemberRoles($guildId, $discordUserId, $finalRoles->all());

        return new RoleSyncResult('synced');
    }

    private function resolveDiscordUserId(Model $user): ?string
    {
        $socialClass = config('discord.social_account_model');
        $provider = (string) config('discord.social_provider');

        if (! method_exists($user, 'socialAccounts')) {
            return null;
        }

        $account = $user->socialAccounts()
            ->where('provider', $provider)
            ->first();

        return $account?->provider_id;
    }

    /**
     * @return Collection<int, string>
     */
    private function desiredDiscordRoleIds(Model $user): Collection
    {
        if (! method_exists($user, 'getRoleNames')) {
            return collect();
        }

        $roleNames = $user->getRoleNames()->all();

        return DiscordRoleMapping::query()
            ->where('sync_to_discord', true)
            ->whereIn('spatie_role_name', $roleNames)
            ->pluck('discord_role_id');
    }

    /**
     * @param  array<string, mixed>  $member
     * @param  Collection<int, string>  $desiredMappedRoles
     * @return Collection<int, string>
     */
    private function mergeRoles(array $member, Collection $desiredMappedRoles): Collection
    {
        /** @var list<string> $current */
        $current = $member['roles'] ?? [];

        if (! config('discord.preserve_unmapped_roles')) {
            return $desiredMappedRoles->values();
        }

        $managedDiscordIds = DiscordRoleMapping::query()
            ->where('sync_to_discord', true)
            ->pluck('discord_role_id')
            ->all();

        $preserved = collect($current)
            ->reject(fn (string $roleId) => in_array($roleId, $managedDiscordIds, true));

        return $preserved->merge($desiredMappedRoles)->unique()->values();
    }
}
