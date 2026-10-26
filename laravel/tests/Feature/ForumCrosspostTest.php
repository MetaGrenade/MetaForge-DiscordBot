<?php

namespace MetaForge\Discord\Tests\Feature;

use Illuminate\Support\Facades\Http;
use MetaForge\Discord\Jobs\CrossPostForumItemJob;
use MetaForge\Discord\Models\DiscordForumCrosspostLog;
use MetaForge\Discord\Tests\TestCase;

class ForumCrosspostTest extends TestCase
{
    public function test_crosspost_job_deduplicates_by_source_key(): void
    {
        config()->set('discord.bot_token', 'token');
        config()->set('discord.forum_crosspost_enabled', true);
        config()->set('discord.forum_crosspost_channel_id', '555');

        DiscordForumCrosspostLog::query()->create([
            'source_key' => 'forum-post:1',
            'message_id' => 'old',
        ]);

        Http::fake();

        $job = new CrossPostForumItemJob(
            ['title' => 'Hello', 'url' => 'https://example.test/t/1'],
            'forum-post:1',
        );
        $job->handle(app(\MetaForge\Discord\Services\ForumCrosspostService::class));

        Http::assertNothingSent();
    }

    public function test_crosspost_respects_board_slug_filter(): void
    {
        config()->set('discord.bot_token', 'token');
        config()->set('discord.forum_crosspost_enabled', true);
        config()->set('discord.forum_crosspost_channel_id', '555');
        config()->set('discord.forum_crosspost_board_slugs', 'announcements');

        Http::fake([
            'https://discord.com/api/v10/channels/555/messages' => Http::response(['id' => '10'], 200),
        ]);

        $job = new CrossPostForumItemJob(
            [
                'title' => 'News',
                'url' => 'https://example.test/t/2',
                'board_slug' => 'general',
            ],
            'forum-post:2',
        );
        $job->handle(app(\MetaForge\Discord\Services\ForumCrosspostService::class));

        Http::assertNothingSent();
    }
}
