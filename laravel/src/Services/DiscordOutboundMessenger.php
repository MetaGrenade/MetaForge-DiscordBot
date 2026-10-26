<?php

namespace MetaForge\Discord\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class DiscordOutboundMessenger
{
    public function __construct(
        private readonly DiscordApiClient $api,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Discord message or webhook body
     * @return array{message_id?: string, channel_id?: string}
     */
    public function sendToChannel(string $channelId, array $payload): array
    {
        return $this->withOutboundLimit(function () use ($channelId, $payload) {
            $response = $this->api->createMessage($channelId, $payload);

            return [
                'message_id' => isset($response['id']) ? (string) $response['id'] : null,
                'channel_id' => isset($response['channel_id']) ? (string) $response['channel_id'] : $channelId,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendToThread(string $threadId, array $payload): array
    {
        return $this->sendToChannel($threadId, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendWebhook(string $webhookUrl, array $payload): void
    {
        $this->withOutboundLimit(function () use ($webhookUrl, $payload) {
            Http::asJson()
                ->post($webhookUrl, $payload)
                ->throw();
        });
    }

    /**
     * @return array{thread_id: string}
     */
    public function startThreadFromMessage(string $channelId, string $messageId, string $threadName): array
    {
        return $this->withOutboundLimit(function () use ($channelId, $messageId, $threadName) {
            $response = $this->api->startThreadFromMessage(
                $channelId,
                $messageId,
                mb_substr($threadName, 0, 100),
                (int) config('discord.support_ticket_thread_auto_archive_minutes', 1440),
            );

            return [
                'thread_id' => (string) ($response['id'] ?? ''),
            ];
        });
    }

    /**
     * @param  callable(): mixed  $callback
     */
    private function withOutboundLimit(callable $callback): mixed
    {
        $key = 'discord-outbound';
        $maxAttempts = (int) config('discord.outbound_rate_limit_per_minute', 30);

        $executed = RateLimiter::attempt(
            $key,
            $maxAttempts,
            function () use ($callback) {
                return $callback();
            },
            60,
        );

        if ($executed === false) {
            Log::warning('Discord outbound rate limit exceeded; message deferred by queue retry.');

            throw new \RuntimeException('Discord outbound rate limit exceeded.');
        }

        return $executed;
    }
}
