<?php

namespace MetaForge\Discord\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use MetaForge\Discord\Jobs\SyncUserDiscordRolesJob;
use MetaForge\Discord\Models\DiscordRoleMapping;
use MetaForge\Discord\Models\DiscordSyncRun;
use MetaForge\Discord\Models\DiscordUserSyncState;

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
     * Queue outbound sync jobs for linked Discord users (chunked; respects unique job dedupe).
     *
     * @return array{queued: int, processed: int}
     */
    public function dispatchSyncJobsForLinkedUsers(string $trigger = DiscordSyncRun::TRIGGER_COMMAND): array
    {
        $run = DiscordSyncRun::query()->create([
            'trigger' => $trigger,
            'status' => DiscordSyncRun::STATUS_RUNNING,
            'meta' => ['mode' => 'queued'],
        ]);

        $counts = ['queued' => 0, 'processed' => 0];
        $chunkSize = max(1, (int) config('discord.role_sync_chunk_size', 100));
        $userClass = config('discord.user_model');
        $socialClass = config('discord.social_account_model');
        $provider = (string) config('discord.social_provider');

        try {
            $socialClass::query()
                ->where('provider', $provider)
                ->orderBy('user_id')
                ->chunk($chunkSize, function ($accounts) use (&$counts, $userClass) {
                    foreach ($accounts as $account) {
                        $counts['processed']++;
                        $user = $userClass::query()->find($account->user_id);

                        if ($user === null) {
                            continue;
                        }

                        SyncUserDiscordRolesJob::dispatchForUser($user);
                        $counts['queued']++;
                    }
                });

            $run->fill([
                'status' => DiscordSyncRun::STATUS_OK,
                'users_processed' => $counts['processed'],
                'users_synced' => $counts['queued'],
                'meta' => ['mode' => 'queued', 'jobs_dispatched' => $counts['queued']],
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

    /**
     * @return array{queued: int, processed: int}
     */
    public function dispatchStaleSyncJobs(?int $hours = null): array
    {
        $hours = $hours ?? (int) config('discord.stale_sync_hours', 24);

        $run = DiscordSyncRun::query()->create([
            'trigger' => DiscordSyncRun::TRIGGER_SCHEDULE,
            'status' => DiscordSyncRun::STATUS_RUNNING,
            'meta' => ['stale_hours' => $hours, 'mode' => 'queued'],
        ]);

        $counts = ['queued' => 0, 'processed' => 0];
        $userClass = config('discord.user_model');

        try {
            foreach ($this->staleLinkedUserIds(now()->subHours($hours)) as $userId) {
                $counts['processed']++;
                $user = $userClass::query()->find($userId);

                if ($user === null) {
                    continue;
                }

                SyncUserDiscordRolesJob::dispatchForUser($user);
                $counts['queued']++;
            }

            $run->fill([
                'status' => DiscordSyncRun::STATUS_OK,
                'users_processed' => $counts['processed'],
                'users_synced' => $counts['queued'],
                'meta' => ['stale_hours' => $hours, 'mode' => 'queued', 'jobs_dispatched' => $counts['queued']],
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

    /**
     * @return array{synced: int, skipped: int, failed: int, processed: int}
     */
    public function syncStaleUsers(bool $dryRun = false, ?int $hours = null): array
    {
        $hours = $hours ?? (int) config('discord.stale_sync_hours', 24);
        $threshold = now()->subHours($hours);

        $run = DiscordSyncRun::query()->create([
            'trigger' => DiscordSyncRun::TRIGGER_SCHEDULE,
            'status' => DiscordSyncRun::STATUS_RUNNING,
            'meta' => ['stale_hours' => $hours],
        ]);

        $counts = ['synced' => 0, 'skipped' => 0, 'failed' => 0, 'processed' => 0];

        try {
            foreach ($this->staleLinkedUserIds($threshold) as $userId) {
                $userClass = config('discord.user_model');
                $user = $userClass::query()->find($userId);

                if ($user === null) {
                    continue;
                }

                $counts['processed']++;
                $result = $this->syncUser($user, $dryRun);

                match ($result->status) {
                    'synced' => $counts['synced']++,
                    'skipped' => $counts['skipped']++,
                    default => $counts['failed']++,
                };
            }

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

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function staleLinkedUserIds(?\Illuminate\Support\Carbon $threshold = null): \Illuminate\Support\Collection
    {
        $threshold ??= now()->subHours((int) config('discord.stale_sync_hours', 24));

        $socialClass = config('discord.social_account_model');
        $provider = (string) config('discord.social_provider');

        $linkedUserIds = $socialClass::query()
            ->where('provider', $provider)
            ->pluck('user_id');

        if ($linkedUserIds->isEmpty()) {
            return collect();
        }

        $syncedRecently = DiscordUserSyncState::query()
            ->whereIn('user_id', $linkedUserIds)
            ->where('last_synced_at', '>=', $threshold)
            ->pluck('user_id');

        return $linkedUserIds->diff($syncedRecently)->values();
    }

    public function syncUser(Model $user, bool $dryRun = false): RoleSyncResult
    {
        if (! config('discord.role_sync_enabled')) {
            $result = new RoleSyncResult('skipped', 'Role sync is disabled.');
            $this->recordSyncState($user, $result);

            return $result;
        }

        $guildId = (string) config('discord.guild_id');
        if ($guildId === '') {
            $result = new RoleSyncResult('skipped', 'DISCORD_GUILD_ID is not configured.');
            $this->recordSyncState($user, $result);

            return $result;
        }

        $discordUserId = $this->resolveDiscordUserId($user);
        if ($discordUserId === null) {
            $result = new RoleSyncResult('skipped', 'User has no linked Discord account.');
            $this->recordSyncState($user, $result);

            return $result;
        }

        $desiredRoles = $this->desiredDiscordRoleIds($user);
        if ($desiredRoles->isEmpty()) {
            $result = new RoleSyncResult('skipped', 'No mapped Spatie roles for this user.');
            $this->recordSyncState($user, $result);

            return $result;
        }

        if ($dryRun) {
            $result = new RoleSyncResult('synced', 'Dry run — would apply '.$desiredRoles->count().' role(s).');
            $this->recordSyncState($user, $result);

            return $result;
        }

        try {
            $member = $this->client->getGuildMember($guildId, $discordUserId);
            if ($member === null) {
                $result = new RoleSyncResult('failed', 'User is not a member of the configured guild.');
                $this->recordSyncState($user, $result);

                return $result;
            }

            $finalRoles = $this->mergeRoles($member, $desiredRoles);

            $this->client->modifyGuildMemberRoles($guildId, $discordUserId, $finalRoles->all());

            $result = new RoleSyncResult('synced');
            $this->recordSyncState($user, $result);

            return $result;
        } catch (\Throwable $exception) {
            $result = new RoleSyncResult('failed', $exception->getMessage());
            $this->recordSyncState($user, $result);

            throw $exception;
        }
    }

    private function recordSyncState(Model $user, RoleSyncResult $result): void
    {
        DiscordUserSyncState::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'last_status' => $result->status,
                'last_message' => $result->message,
                'last_synced_at' => now(),
            ],
        );
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
