<?php

namespace MetaForge\Discord\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
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
            Log::warning('Discord inbound role sync failed', [
                'discord_user_id' => $this->discordUserId,
                'message' => $result->message,
            ]);
        }
    }
}
