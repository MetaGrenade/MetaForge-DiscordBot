<?php

namespace MetaForge\Discord\Services;

use Illuminate\Support\Facades\Log;
use MetaForge\Discord\Models\DiscordSupportTicketThread;
use MetaForge\Discord\Support\SupportTicketMirrorPayload;

class SupportTicketMirrorService
{
    public function __construct(
        private readonly DiscordOutboundMessenger $messenger,
    ) {}

    public function mirror(SupportTicketMirrorPayload $payload): void
    {
        if (! config('discord.support_ticket_mirror_enabled', true)) {
            return;
        }

        $channelId = (string) config('discord.support_ticket_channel_id', '');
        $webhookUrl = (string) config('discord.support_ticket_webhook_url', '');
        $useWebhook = $webhookUrl !== '' && $channelId === '';

        if ($channelId === '' && ! $useWebhook) {
            $fallback = (string) config('discord.default_webhook_url', '');
            if ($fallback === '') {
                Log::debug('Skipping support ticket mirror — no channel or webhook configured.', [
                    'ticket_id' => $payload->ticketId,
                    'kind' => $payload->kind,
                ]);

                return;
            }

            $webhookUrl = $fallback;
            $useWebhook = true;
        }

        $message = $this->buildMessagePayload($payload);

        if ($useWebhook) {
            $this->messenger->sendWebhook($webhookUrl, $message);

            return;
        }

        $threadPerTicket = (bool) config('discord.support_ticket_thread_per_ticket', false);

        if (! $threadPerTicket) {
            $this->messenger->sendToChannel($channelId, $message);

            return;
        }

        $record = DiscordSupportTicketThread::query()->firstOrCreate(
            ['ticket_id' => $payload->ticketId],
            ['channel_id' => $channelId],
        );

        if ($record->thread_id) {
            $this->messenger->sendToThread($record->thread_id, $message);
            $record->update(['last_event_type' => $payload->kind]);

            return;
        }

        $posted = $this->messenger->sendToChannel($channelId, $message);
        $starterMessageId = $posted['message_id'] ?? null;

        if ($starterMessageId === null || $starterMessageId === '') {
            return;
        }

        $threadName = sprintf('ticket-%d', $payload->ticketId);
        $thread = $this->messenger->startThreadFromMessage($channelId, $starterMessageId, $threadName);

        $record->update([
            'starter_message_id' => $starterMessageId,
            'thread_id' => $thread['thread_id'] ?? null,
            'last_event_type' => $payload->kind,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMessagePayload(SupportTicketMirrorPayload $payload): array
    {
        $title = match ($payload->kind) {
            'opened' => 'Support ticket opened',
            'reply' => 'New support ticket reply',
            'closed' => 'Support ticket closed',
            default => 'Support ticket updated',
        };

        $fields = [
            ['name' => 'Ticket', 'value' => '#'.$payload->ticketId, 'inline' => true],
            ['name' => 'Status', 'value' => $payload->status, 'inline' => true],
        ];

        if ($payload->excerpt !== null && $payload->excerpt !== '') {
            $fields[] = ['name' => 'Excerpt', 'value' => $payload->excerpt, 'inline' => false];
        }

        $embed = [
            'title' => $title,
            'description' => mb_substr($payload->subject, 0, 256),
            'fields' => $fields,
            'color' => match ($payload->kind) {
                'opened' => 0x3498DB,
                'reply' => 0xF1C40F,
                'closed' => 0x95A5A6,
                default => 0x5865F2,
            },
        ];

        if ($payload->ticketUrl !== null && $payload->ticketUrl !== '') {
            $embed['url'] = $payload->ticketUrl;
        }

        return [
            'embeds' => [$embed],
            'allowed_mentions' => ['parse' => []],
        ];
    }
}
