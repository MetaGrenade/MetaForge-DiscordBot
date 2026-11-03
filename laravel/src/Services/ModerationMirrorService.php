<?php

namespace MetaForge\Discord\Services;

use Illuminate\Support\Facades\Log;
use MetaForge\Discord\Support\ModerationMirrorPayload;

class ModerationMirrorService
{
    public function __construct(
        private readonly DiscordOutboundMessenger $messenger,
    ) {}

    public function mirror(ModerationMirrorPayload $payload): void
    {
        if (! config('discord.moderation_mirror_enabled', true)) {
            return;
        }

        $channelId = (string) config('discord.moderation_channel_id', '');
        $webhookUrl = (string) config('discord.moderation_webhook_url', '');
        $useWebhook = $webhookUrl !== '' && $channelId === '';

        if ($channelId === '' && ! $useWebhook) {
            Log::debug('Skipping moderation mirror — no channel or webhook configured.', [
                'action_id' => $payload->actionId,
                'type' => $payload->type,
                'user_id' => $payload->memberUserId,
            ]);

            return;
        }

        $message = $this->buildMessagePayload($payload);

        if ($useWebhook) {
            $this->messenger->sendWebhook($webhookUrl, $message);

            return;
        }

        $this->messenger->sendToChannel($channelId, $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMessagePayload(ModerationMirrorPayload $payload): array
    {
        $title = match ($payload->type) {
            'warning' => 'Member warned',
            'ban' => 'Member banned',
            'unban' => 'Ban lifted',
            'removal' => 'Member content removed',
            default => 'Moderation action',
        };

        $fields = [
            ['name' => 'Action', 'value' => '#'.$payload->actionId, 'inline' => true],
            ['name' => 'Type', 'value' => $payload->type, 'inline' => true],
            ['name' => 'Member user ID', 'value' => (string) $payload->memberUserId, 'inline' => true],
        ];

        if ($payload->actorUserId !== null) {
            $fields[] = ['name' => 'Actor user ID', 'value' => (string) $payload->actorUserId, 'inline' => true];
        }

        if ($payload->subjectLabel !== null && $payload->subjectLabel !== '') {
            $fields[] = ['name' => 'Subject', 'value' => $payload->subjectLabel, 'inline' => false];
        }

        if ($payload->reasonExcerpt !== null && $payload->reasonExcerpt !== '') {
            $fields[] = ['name' => 'Reason (excerpt)', 'value' => $payload->reasonExcerpt, 'inline' => false];
        }

        $embed = [
            'title' => $title,
            'description' => 'Staff-only metadata — open MetaForge ACP for full context.',
            'fields' => $fields,
            'color' => match ($payload->type) {
                'warning' => 0xF1C40F,
                'ban' => 0xE74C3C,
                'unban' => 0x2ECC71,
                'removal' => 0x95A5A6,
                default => 0x5865F2,
            },
        ];

        if ($payload->actionUrl !== null && $payload->actionUrl !== '') {
            $embed['url'] = $payload->actionUrl;
        }

        return [
            'embeds' => [$embed],
            'allowed_mentions' => ['parse' => []],
        ];
    }
}
