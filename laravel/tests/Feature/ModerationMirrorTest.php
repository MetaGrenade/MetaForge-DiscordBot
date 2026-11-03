<?php

namespace MetaForge\Discord\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use MetaForge\Discord\Jobs\NotifyDiscordModerationJob;
use MetaForge\Discord\Services\ModerationEventExtractor;
use MetaForge\Discord\Services\ModerationMirrorService;
use MetaForge\Discord\Support\ModerationMirrorPayload;
use MetaForge\Discord\Tests\TestCase;

class ModerationMirrorTest extends TestCase
{
    public function test_moderation_webhook_queues_mirror_job(): void
    {
        Queue::fake();
        config()->set('discord.bot_shared_secret', 'secret');

        $body = json_encode([
            'action_id' => 7,
            'user_id' => 42,
            'type' => 'warning',
            'actor_id' => 1,
        ], JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'secret');

        $this->call(
            'POST',
            '/discord/bot/moderation',
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

        Queue::assertPushed(NotifyDiscordModerationJob::class, function (NotifyDiscordModerationJob $job) {
            return $job->payload->actionId === 7
                && $job->payload->type === 'warning'
                && $job->payload->memberUserId === 42;
        });
    }

    public function test_mirror_job_posts_embed_without_reason_by_default(): void
    {
        config()->set('discord.bot_token', 'token');
        config()->set('discord.moderation_channel_id', '888');
        config()->set('discord.moderation_staff_reason_in_embed', false);

        Http::fake([
            'https://discord.com/api/v10/channels/888/messages' => Http::response(['id' => '1', 'channel_id' => '888'], 200),
        ]);

        $job = new NotifyDiscordModerationJob(new ModerationMirrorPayload(
            actionId: 3,
            type: 'ban',
            memberUserId: 99,
            actorUserId: 2,
        ));

        $job->handle(app(ModerationMirrorService::class));

        Http::assertSent(function ($request) {
            $embed = $request->data()['embeds'][0] ?? [];
            $fieldNames = array_column($embed['fields'] ?? [], 'name');

            return str_contains($request->url(), '/channels/888/messages')
                && ($embed['title'] ?? '') === 'Member banned'
                && ! in_array('Reason (excerpt)', $fieldNames, true);
        });
    }

    public function test_event_extractor_omits_reason_unless_config_enabled(): void
    {
        config()->set('discord.moderation_staff_reason_in_embed', false);

        $extractor = app(ModerationEventExtractor::class);

        $event = new class
        {
            public function moderationAction(): object
            {
                return (object) [
                    'id' => 1,
                    'user_id' => 5,
                    'actor_id' => 2,
                    'type' => 'warning',
                    'reason' => 'Do not share private data in chat.',
                ];
            }
        };

        $payload = $extractor->fromEvent($event);

        $this->assertNotNull($payload);
        $this->assertNull($payload->reasonExcerpt);
    }
}
