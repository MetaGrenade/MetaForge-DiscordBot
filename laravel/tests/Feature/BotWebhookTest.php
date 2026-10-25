<?php

namespace MetaForge\Discord\Tests\Feature;

use MetaForge\Discord\Tests\TestCase;

class BotWebhookTest extends TestCase
{
    public function test_rejects_unsigned_bot_requests(): void
    {
        config()->set('discord.bot_shared_secret', 'secret');

        $this->postJson('/discord/bot/events', ['type' => 'bot.ready'])
            ->assertUnauthorized();
    }

    public function test_accepts_signed_bot_events(): void
    {
        config()->set('discord.bot_shared_secret', 'secret');

        $body = json_encode(['type' => 'bot.ready', 'payload' => ['ok' => true]], JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'secret');

        $this->call(
            'POST',
            '/discord/bot/events',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_DISCORD_BOT_TIMESTAMP' => $timestamp,
                'HTTP_X_DISCORD_BOT_SIGNATURE' => $signature,
            ],
            $body,
        )->assertOk()->assertJson(['ok' => true]);
    }
}
