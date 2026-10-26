<?php

namespace MetaForge\Discord\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use MetaForge\Discord\Jobs\NotifyDiscordSupportTicketJob;
use MetaForge\Discord\Support\SupportTicketMirrorPayload;
use MetaForge\Discord\Tests\TestCase;

class SupportTicketMirrorTest extends TestCase
{
    public function test_support_ticket_webhook_queues_mirror_job(): void
    {
        Queue::fake();
        config()->set('discord.bot_shared_secret', 'secret');

        $body = json_encode([
            'ticket_id' => 99,
            'kind' => 'reply',
            'subject' => 'Help',
            'status' => 'open',
        ], JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'secret');

        $this->call(
            'POST',
            '/discord/bot/support-tickets',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_DISCORD_BOT_TIMESTAMP' => $timestamp,
                'HTTP_X_DISCORD_BOT_SIGNATURE' => $signature,
            ],
            $body,
        )->assertOk()->assertJson(['queued' => true]);

        Queue::assertPushed(NotifyDiscordSupportTicketJob::class, function (NotifyDiscordSupportTicketJob $job) {
            return $job->payload->ticketId === 99 && $job->payload->kind === 'reply';
        });
    }

    public function test_mirror_job_posts_embed_to_configured_channel(): void
    {
        config()->set('discord.bot_token', 'token');
        config()->set('discord.support_ticket_channel_id', '999');
        config()->set('discord.support_ticket_thread_per_ticket', false);

        Http::fake([
            'https://discord.com/api/v10/channels/999/messages' => Http::response(['id' => '1', 'channel_id' => '999'], 200),
        ]);

        $job = new NotifyDiscordSupportTicketJob(new SupportTicketMirrorPayload(
            ticketId: 5,
            kind: 'opened',
            subject: 'Billing',
            status: 'open',
        ));

        $job->handle(app(\MetaForge\Discord\Services\SupportTicketMirrorService::class));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/channels/999/messages')
                && isset($request->data()['embeds'][0]['title']);
        });
    }

    public function test_staff_summary_requires_staff_role(): void
    {
        config()->set('discord.bot_shared_secret', 'secret');
        config()->set('discord.support_staff_discord_role_ids', ['staff-role']);

        $body = json_encode([
            'discord_user_id' => '1',
            'ticket_id' => 1,
            'member_role_ids' => ['other'],
        ], JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'secret');

        $this->call(
            'POST',
            '/discord/bot/support-tickets/summary',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_DISCORD_BOT_TIMESTAMP' => $timestamp,
                'HTTP_X_DISCORD_BOT_SIGNATURE' => $signature,
            ],
            $body,
        )->assertForbidden();
    }
}
