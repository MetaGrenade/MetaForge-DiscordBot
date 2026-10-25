<?php

namespace MetaForge\Discord\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use MetaForge\Discord\Services\RoleSyncService;

class SyncUserDiscordRolesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  class-string<Model>  $userClass
     */
    public function __construct(
        public int $userId,
        public string $userClass,
    ) {}

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
