<?php

namespace MetaForge\Discord\Tests\Feature;

use MetaForge\Discord\Tests\TestCase;

class BotWebhookSecurityTest extends TestCase
{
    public function test_rejects_when_shared_secret_not_configured(): void
    {
        config()->set('discord.bot_shared_secret', '');

        $this->postJson('/discord/bot/events', ['type' => 'bot.ready'])
            ->assertStatus(503);
    }

    public function test_rejects_missing_signature_headers(): void
    {
        config()->set('discord.bot_shared_secret', 'secret');

        $this->postJson('/discord/bot/events', ['type' => 'bot.ready'])
            ->assertUnauthorized();
    }

    public function test_rejects_stale_timestamp(): void
    {
        config()->set('discord.bot_shared_secret', 'secret');
        config()->set('discord.webhook_max_skew_seconds', 60);

        $body = json_encode(['type' => 'bot.ready'], JSON_THROW_ON_ERROR);
        $timestamp = (string) (time() - 3600);
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
        )->assertUnauthorized();
    }

    public function test_rejects_invalid_signature(): void
    {
        config()->set('discord.bot_shared_secret', 'secret');

        $body = json_encode(['type' => 'bot.ready'], JSON_THROW_ON_ERROR);
        $timestamp = (string) time();

        $this->call(
            'POST',
            '/discord/bot/events',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_DISCORD_BOT_TIMESTAMP' => $timestamp,
                'HTTP_X_DISCORD_BOT_SIGNATURE' => 'not-valid',
            ],
            $body,
        )->assertUnauthorized();
    }
}
