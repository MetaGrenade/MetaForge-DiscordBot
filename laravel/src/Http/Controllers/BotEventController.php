<?php

namespace MetaForge\Discord\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MetaForge\Discord\Models\DiscordSyncRun;

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
            default => Log::debug('Discord bot event received', ['type' => $type, 'payload' => $payload]),
        };

        DiscordSyncRun::query()->create([
            'trigger' => DiscordSyncRun::TRIGGER_BOT,
            'status' => DiscordSyncRun::STATUS_OK,
            'users_processed' => 0,
            'meta' => ['event' => $type, 'payload' => $payload],
            'finished_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
