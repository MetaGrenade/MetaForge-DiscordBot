<?php

namespace MetaForge\Discord\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use MetaForge\Discord\Models\DiscordSyncFailure;
use MetaForge\Discord\Support\DiscordObservability;
use MetaForge\Discord\Support\DiscordStructuredLog;
use MetaForge\Discord\Services\RoleSyncService;

class SyncUserDiscordRolesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 300;

    /**
     * @param  class-string<Model>  $userClass
     */
    public function __construct(
        public int $userId,
        public string $userClass,
    ) {}

    public function uniqueId(): string
    {
        return 'discord-role-sync:'.$this->userClass.':'.$this->userId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        $middleware = [
            new RateLimited('discord-api'),
            (new WithoutOverlapping($this->uniqueId()))->expireAfter(120),
        ];

        return $middleware;
    }

    public function handle(RoleSyncService $sync): void
    {
        $user = ($this->userClass)::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $result = $sync->syncUser($user);

        if ($result->status === 'failed') {
            DiscordStructuredLog::warning('discord.role_sync.outbound.failed', [
                'user_id' => $this->userId,
                'message' => $result->message,
            ]);
            DiscordObservability::recordCounter('discord.role_sync.outbound.failed', [
                'user_id' => $this->userId,
            ]);

            DiscordSyncFailure::record(
                DiscordSyncFailure::OPERATION_OUTBOUND_ROLE_SYNC,
                (string) ($result->message ?? 'Outbound role sync failed.'),
                $this->userId,
                null,
                (string) config('discord.guild_id') ?: null,
                [
                    'user_id' => $this->userId,
                    'user_class' => $this->userClass,
                ],
            );
        } elseif ($result->status === 'synced') {
            DiscordStructuredLog::info('discord.role_sync.outbound.synced', [
                'user_id' => $this->userId,
            ]);
            DiscordObservability::recordCounter('discord.role_sync.outbound.synced', [
                'user_id' => $this->userId,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        DiscordSyncFailure::record(
            DiscordSyncFailure::OPERATION_OUTBOUND_ROLE_SYNC,
            $exception->getMessage(),
            $this->userId,
            null,
            (string) config('discord.guild_id') ?: null,
            [
                'user_id' => $this->userId,
                'user_class' => $this->userClass,
            ],
        );

        DiscordStructuredLog::warning('discord.role_sync.outbound.job_failed', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }

    public static function dispatchForUser(Model $user): void
    {
        dispatch(new self($user->getKey(), $user::class));
    }
}
