<?php

namespace MetaForge\Discord\Tests\Feature;

use Illuminate\Notifications\Notification;
use MetaForge\Discord\Contracts\DiscordNotifiableNotification;
use MetaForge\Discord\Models\DiscordNotificationPreference;
use MetaForge\Discord\Notifications\Channels\DiscordWebhookChannel;
use MetaForge\Discord\Tests\TestCase;
use MetaForge\Discord\Tests\TestUser;
use Illuminate\Support\Facades\Http;

class NotificationPreferenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('discord.user_middleware', []);
        config()->set('discord.default_webhook_url', 'https://discord.com/api/webhooks/test');
    }

    public function test_user_can_update_notification_preferences(): void
    {
        $user = TestUser::query()->create(['name' => 'E', 'email' => 'e@example.test']);

        $this->actingAs($user)
            ->putJson('/discord/user/notification-preferences', [
                'preferences' => [
                    'support_ticket_reply' => true,
                    'order_shipped' => false,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('preferences.support_ticket_reply', true);

        $this->assertDatabaseHas('discord_notification_preferences', [
            'user_id' => $user->id,
            'category' => 'support_ticket_reply',
            'enabled' => true,
        ]);
    }

    public function test_discord_channel_respects_opt_in(): void
    {
        Http::fake();

        $user = TestUser::query()->create(['name' => 'F', 'email' => 'f@example.test']);

        DiscordNotificationPreference::query()->create([
            'user_id' => $user->id,
            'category' => 'support_ticket_reply',
            'enabled' => false,
        ]);

        $notification = new class extends Notification implements DiscordNotifiableNotification
        {
            public function discordNotificationCategory(): string
            {
                return 'support_ticket_reply';
            }

            public function toDiscord(object $notifiable): array
            {
                return ['content' => 'Should not send'];
            }
        };

        app(DiscordWebhookChannel::class)->send($user, $notification);

        Http::assertNothingSent();
    }
}
