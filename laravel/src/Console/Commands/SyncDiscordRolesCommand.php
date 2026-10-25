<?php

namespace MetaForge\Discord\Console\Commands;

use Illuminate\Console\Command;
use MetaForge\Discord\Jobs\SyncUserDiscordRolesJob;
use MetaForge\Discord\Services\DiscordApiException;
use MetaForge\Discord\Services\RoleSyncService;

class SyncDiscordRolesCommand extends Command
{
    protected $signature = 'discord:sync-roles
                            {--user= : Sync a single user id}
                            {--dry-run : Compute changes without calling Discord}
                            {--sync : Run synchronously instead of dispatching a queue job}';

    protected $description = 'Sync Spatie roles to Discord for linked members';

    public function handle(RoleSyncService $sync): int
    {
        $userId = $this->option('user');
        $dryRun = (bool) $this->option('dry-run');

        try {
            if ($userId !== null && $userId !== '') {
                return $this->syncOne($sync, (int) $userId, $dryRun);
            }

            $counts = $sync->syncAllUsers($dryRun);
        } catch (DiscordApiException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Count'],
            collect($counts)->map(fn ($value, $key) => [$key, $value])->values()->all(),
        );

        return self::SUCCESS;
    }

    private function syncOne(RoleSyncService $sync, int $userId, bool $dryRun): int
    {
        $userClass = config('discord.user_model');
        $user = $userClass::query()->find($userId);

        if ($user === null) {
            $this->components->error('User not found.');

            return self::FAILURE;
        }

        if ($this->option('sync')) {
            $result = $sync->syncUser($user, $dryRun);
            $this->components->info($result->status.($result->message ? ': '.$result->message : ''));

            return $result->status === 'failed' ? self::FAILURE : self::SUCCESS;
        }

        if ($dryRun) {
            $result = $sync->syncUser($user, true);
            $this->components->info($result->status.($result->message ? ': '.$result->message : ''));

            return self::SUCCESS;
        }

        SyncUserDiscordRolesJob::dispatchForUser($user);
        $this->components->info('Queued Discord role sync for user '.$userId.'.');

        return self::SUCCESS;
    }
}
