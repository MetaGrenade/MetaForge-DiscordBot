<?php

namespace MetaForge\Discord\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MetaForge\Discord\Jobs\ApplyDiscordInboundRolesJob;
use MetaForge\Discord\Models\DiscordSyncRun;
use MetaForge\Discord\Services\DiscordInboundRoleSyncService;

class BotEventController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{type?: string, payload?: array<string, mixed>} $data */
        $data = $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'payload' => ['nullable', 'array'],
        ]);

        $type = $data['type'];
        $payload = $data['payload'] ?? [];

        match ($type) {
            'bot.ready' => Log::info('Discord bot connected', $payload),
            'guild.member.add' => Log::info('Discord member joined guild', $payload),
            'guild.member.update' => $this->handleMemberUpdate($payload),
            'support.ticket.thread_created' => Log::info('Support ticket thread hook (future mirroring)', $payload),
            default => Log::debug('Discord bot event received', ['type' => $type, 'payload' => $payload]),
        };

        if ($type !== 'guild.member.update') {
            DiscordSyncRun::query()->create([
                'trigger' => DiscordSyncRun::TRIGGER_BOT,
                'status' => DiscordSyncRun::STATUS_OK,
                'users_processed' => 0,
                'meta' => ['event' => $type, 'payload' => $payload],
                'finished_at' => now(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleMemberUpdate(array $payload): void
    {
        $discordUserId = isset($payload['discord_user_id']) ? (string) $payload['discord_user_id'] : '';
        $guildId = isset($payload['guild_id']) ? (string) $payload['guild_id'] : '';
        /** @var list<string>|mixed $roles */
        $roles = $payload['roles'] ?? [];

        if ($discordUserId === '' || $guildId === '' || ! is_array($roles)) {
            Log::warning('Invalid guild.member.update payload', ['payload' => $payload]);

            return;
        }

        /** @var list<string> $roleIds */
        $roleIds = array_values(array_map(strval(...), $roles));

        if (config('discord.inbound_sync_queue', true)) {
            ApplyDiscordInboundRolesJob::dispatch($discordUserId, $roleIds, $guildId);

            return;
        }

        app(DiscordInboundRoleSyncService::class)->applyMemberRoles($discordUserId, $roleIds, $guildId);
    }
}
