<?php

namespace MetaForge\Discord\Services;

use Illuminate\Support\Facades\Log;
use MetaForge\Discord\Models\DiscordForumCrosspostLog;

class ForumCrosspostService
{
    public function __construct(
        private readonly DiscordOutboundMessenger $messenger,
    ) {}

    /**
     * @param  array{title: string, url: string, excerpt?: string|null, board_slug?: string|null}  $item
     */
    public function crosspost(array $item, string $sourceKey): void
    {
        if (! config('discord.forum_crosspost_enabled', false)) {
            return;
        }

        if ($this->isFilteredOut($item)) {
            return;
        }

        if (DiscordForumCrosspostLog::query()->where('source_key', $sourceKey)->exists()) {
            return;
        }

        $channelId = (string) config('discord.forum_crosspost_channel_id', '');
        $webhookUrl = (string) config('discord.forum_crosspost_webhook_url', '');

        if ($channelId === '' && $webhookUrl === '') {
            Log::debug('Skipping forum cross-post — channel or webhook not configured.', [
                'source_key' => $sourceKey,
            ]);

            return;
        }

        $payload = [
            'embeds' => [[
                'title' => mb_substr($item['title'], 0, 256),
                'description' => isset($item['excerpt']) ? mb_substr((string) $item['excerpt'], 0, 512) : null,
                'url' => $item['url'],
                'color' => 0x57F287,
            ]],
            'allowed_mentions' => ['parse' => []],
        ];

        $messageId = null;

        if ($channelId !== '') {
            $result = $this->messenger->sendToChannel($channelId, $payload);
            $messageId = $result['message_id'] ?? null;
        } else {
            $this->messenger->sendWebhook($webhookUrl, $payload);
        }

        DiscordForumCrosspostLog::query()->create([
            'source_key' => $sourceKey,
            'message_id' => $messageId,
        ]);
    }

    /**
     * @param  array{title: string, url: string, excerpt?: string|null, board_slug?: string|null}  $item
     */
    private function isFilteredOut(array $item): bool
    {
        /** @var list<string> $allowedBoards */
        $allowedBoards = array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) config('discord.forum_crosspost_board_slugs', '')),
        )));

        if ($allowedBoards === []) {
            return false;
        }

        $slug = isset($item['board_slug']) ? (string) $item['board_slug'] : '';

        return $slug === '' || ! in_array($slug, $allowedBoards, true);
    }
}
