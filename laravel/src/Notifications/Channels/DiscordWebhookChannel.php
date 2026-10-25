<?php

namespace MetaForge\Discord\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class DiscordWebhookChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toDiscord')) {
            return;
        }

        /** @var array{content?: string, embeds?: list<array<string, mixed>>, username?: string}|null $message */
        $message = $notification->toDiscord($notifiable);

        if ($message === null) {
            return;
        }

        $webhookUrl = $message['webhook_url'] ?? config('discord.default_webhook_url');

        if (! is_string($webhookUrl) || $webhookUrl === '') {
            return;
        }

        unset($message['webhook_url']);

        Http::asJson()->post($webhookUrl, $message)->throw();
    }
}
