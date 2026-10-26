<?php

namespace MetaForge\Discord\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use MetaForge\Discord\Jobs\SendDiscordWelcomeMessageJob;
use MetaForge\Discord\Tests\TestCase;

class WelcomeMessageTest extends TestCase
{
    public function test_member_add_dispatches_welcome_job_when_enabled(): void
    {
        Queue::fake();
        config()->set('discord.bot_shared_secret', 'secret');
        config()->set('discord.welcome_enabled', true);

        $body = json_encode([
            'type' => 'guild.member.add',
            'payload' => ['discord_user_id' => '42', 'guild_id' => '123456789012345678'],
        ], JSON_THROW_ON_ERROR);
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
        )->assertOk();

        Queue::assertPushed(SendDiscordWelcomeMessageJob::class, fn (SendDiscordWelcomeMessageJob $job) => $job->discordUserId === '42');
    }

    public function test_welcome_skips_unlinked_when_linked_only(): void
    {
        config()->set('discord.bot_token', 'token');
        config()->set('discord.welcome_enabled', true);
        config()->set('discord.welcome_channel_id', '777');
        config()->set('discord.welcome_linked_only', true);

        Http::fake();

        $job = new SendDiscordWelcomeMessageJob('999');
        $job->handle(app(\MetaForge\Discord\Services\WelcomeMessageService::class));

        Http::assertNothingSent();
    }
}
