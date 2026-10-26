<?php

namespace MetaForge\Discord\Services;

use Illuminate\Support\Facades\Log;

class WelcomeMessageService
{
    public function __construct(
        private readonly DiscordOutboundMessenger $messenger,
        private readonly DiscordMemberLookupService $members,
    ) {}

    public function sendForMember(string $discordUserId): void
    {
        if (! config('discord.welcome_enabled', false)) {
            return;
        }

        $channelId = (string) config('discord.welcome_channel_id', '');
        if ($channelId === '') {
            return;
        }

        $linkedOnly = (bool) config('discord.welcome_linked_only', true);
        $link = $this->members->linkStatus($discordUserId);

        if ($linkedOnly && ! $link['linked']) {
            return;
        }

        $template = (string) config(
            'discord.welcome_message_template',
            'Welcome <@{discord_user_id}> — link your MetaForge account via Discord OAuth on the site.',
        );

        $content = str_replace(
            ['{discord_user_id}', '{user_id}', '{user_name}'],
            [
                $discordUserId,
                isset($link['user']['id']) ? (string) $link['user']['id'] : '',
                isset($link['user']['name']) ? (string) $link['user']['name'] : 'there',
            ],
            $template,
        );

        try {
            $this->messenger->sendToChannel($channelId, [
                'content' => mb_substr($content, 0, 2000),
                'allowed_mentions' => ['users' => [$discordUserId]],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to send Discord welcome message.', [
                'discord_user_id' => $discordUserId,
                'exception' => $exception::class,
            ]);
        }
    }
}
