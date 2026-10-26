<?php

namespace MetaForge\Discord\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use MetaForge\Discord\Jobs\ApplyDiscordInboundRolesJob;
use MetaForge\Discord\Models\DiscordRoleMapping;
use MetaForge\Discord\Services\DiscordInboundRoleSyncService;
use MetaForge\Discord\Tests\TestCase;
use MetaForge\Discord\Tests\TestSocialAccount;
use MetaForge\Discord\Tests\TestUser;
use Spatie\Permission\Models\Role;

class DiscordInboundRoleSyncTest extends TestCase
{
    public function test_applies_discord_roles_to_spatie_for_sync_from_discord_mappings(): void
    {
        config()->set('discord.sync_from_discord_enabled', true);
        config()->set('discord.inbound_conflict_policy', 'discord_wins');

        $user = TestUser::query()->create(['name' => 'Inbound', 'email' => 'in@example.test']);
        TestSocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'discord',
            'provider_id' => '111222333444555666',
        ]);

        Role::findOrCreate('subscriber');
        DiscordRoleMapping::query()->create([
            'spatie_role_name' => 'subscriber',
            'discord_role_id' => '9001',
            'sync_to_discord' => false,
            'sync_from_discord' => true,
        ]);

        $result = app(DiscordInboundRoleSyncService::class)->applyMemberRoles(
            '111222333444555666',
            ['9001', '@everyone'],
            '123456789012345678',
        );

        $this->assertSame('synced', $result->status);
        $this->assertTrue($user->fresh()->hasRole('subscriber'));
    }

    public function test_app_wins_policy_skips_inbound_changes(): void
    {
        config()->set('discord.inbound_conflict_policy', 'app_wins');

        $user = TestUser::query()->create(['name' => 'AppWins', 'email' => 'aw@example.test']);
        TestSocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'discord',
            'provider_id' => '222333444555666777',
        ]);

        Role::findOrCreate('vip');
        DiscordRoleMapping::query()->create([
            'spatie_role_name' => 'vip',
            'discord_role_id' => '8008',
            'sync_from_discord' => true,
        ]);

        $result = app(DiscordInboundRoleSyncService::class)->applyMemberRoles(
            '222333444555666777',
            ['8008'],
            '123456789012345678',
        );

        $this->assertSame('skipped', $result->status);
        $this->assertFalse($user->fresh()->hasRole('vip'));
    }

    public function test_guild_member_update_event_queues_inbound_job(): void
    {
        Bus::fake([ApplyDiscordInboundRolesJob::class]);

        config()->set('discord.bot_shared_secret', 'secret');
        config()->set('discord.inbound_sync_queue', true);

        $body = json_encode([
            'type' => 'guild.member.update',
            'payload' => [
                'discord_user_id' => '1',
                'guild_id' => '123456789012345678',
                'roles' => ['100', '200'],
            ],
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

        Bus::assertDispatched(ApplyDiscordInboundRolesJob::class);
    }
}
