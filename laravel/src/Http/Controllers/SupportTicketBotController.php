<?php

namespace MetaForge\Discord\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MetaForge\Discord\Jobs\NotifyDiscordSupportTicketJob;
use MetaForge\Discord\Services\DiscordStaffAccessService;
use MetaForge\Discord\Services\SupportTicketEventExtractor;

class SupportTicketBotController
{
    public function ingest(Request $request, SupportTicketEventExtractor $extractor): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'ticket_id' => ['required', 'integer', 'min:1'],
            'kind' => ['nullable', 'string', 'in:opened,reply,updated,closed'],
            'subject' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:64'],
            'url' => ['nullable', 'string', 'max:2048'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'user_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $payload = $extractor->fromWebhook($data);
        if ($payload === null) {
            return response()->json(['ok' => false, 'message' => 'Invalid payload.'], 422);
        }

        NotifyDiscordSupportTicketJob::dispatch($payload);

        return response()->json(['ok' => true, 'queued' => true]);
    }

    public function summary(Request $request, DiscordStaffAccessService $staff): JsonResponse
    {
        $data = $request->validate([
            'discord_user_id' => ['required', 'string', 'max:32'],
            'ticket_id' => ['required', 'integer', 'min:1'],
            'member_role_ids' => ['nullable', 'array'],
            'member_role_ids.*' => ['string', 'max:32'],
        ]);

        /** @var list<string> $roleIds */
        $roleIds = array_values(array_map(strval(...), $data['member_role_ids'] ?? []));

        if (! $staff->canAccessSupportTools($data['discord_user_id'], $roleIds)) {
            return response()->json(['ok' => false, 'message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'ok' => true,
            'ticket_id' => (int) $data['ticket_id'],
            'summary' => sprintf(
                'Ticket #%d — open MetaForge ACP/support for full details (Discord mirror is staff-only metadata).',
                (int) $data['ticket_id'],
            ),
        ]);
    }
}
