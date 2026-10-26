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
use Illuminate\Support\Facades\Log;
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
            Log::warning('Discord role sync failed for user '.$this->userId, [
                'message' => $result->message,
            ]);
        }
    }

    public static function dispatchForUser(Model $user): void
    {
        dispatch(new self($user->getKey(), $user::class));
    }
}
