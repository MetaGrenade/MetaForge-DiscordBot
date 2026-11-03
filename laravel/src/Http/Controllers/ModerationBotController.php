<?php

namespace MetaForge\Discord\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MetaForge\Discord\Jobs\NotifyDiscordModerationJob;
use MetaForge\Discord\Services\ModerationEventExtractor;

class ModerationBotController
{
    public function ingest(Request $request, ModerationEventExtractor $extractor): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'action_id' => ['required', 'integer', 'min:1'],
            'user_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', 'in:warning,ban,unban,removal'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'subject_label' => ['nullable', 'string', 'max:255'],
            'reason_excerpt' => ['nullable', 'string', 'max:500'],
            'url' => ['nullable', 'string', 'max:2048'],
        ]);

        $payload = $extractor->fromWebhook($data);
        if ($payload === null) {
            return response()->json(['ok' => false, 'message' => 'Invalid payload.'], 422);
        }

        NotifyDiscordModerationJob::dispatch($payload);

        return response()->json(['ok' => true, 'queued' => true]);
    }
}
