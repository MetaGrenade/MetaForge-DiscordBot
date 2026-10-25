<?php

namespace MetaForge\Discord\Tests\Feature;

use MetaForge\Discord\Models\DiscordRoleMapping;
use MetaForge\Discord\Tests\TestCase;
use MetaForge\Discord\Tests\TestSocialAccount;
use MetaForge\Discord\Tests\TestUser;
use Spatie\Permission\Models\Role;

class BotMemberApiTest extends TestCase
{
    private function signedPost(string $path, array $payload)
    {
        config()->set('discord.bot_shared_secret', 'secret');

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'secret');

        return $this->call(
            'POST',
            $path,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_DISCORD_BOT_TIMESTAMP' => $timestamp,
                'HTTP_X_DISCORD_BOT_SIGNATURE' => $signature,
            ],
            $body,
        );
    }

    public function test_link_status_for_linked_member(): void
    {
        $user = TestUser::query()->create(['name' => 'Caleb', 'email' => 'c@example.test']);
        TestSocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'discord',
            'provider_id' => '111222333444555666',
        ]);

        $this->signedPost('/discord/bot/members/link-status', [
            'discord_user_id' => '111222333444555666',
        ])->assertOk()
            ->assertJsonPath('linked', true)
            ->assertJsonPath('user.name', 'Caleb');
    }

    public function test_roles_for_linked_member(): void
    {
        $user = TestUser::query()->create(['name' => 'D', 'email' => 'd@example.test']);
        TestSocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'discord',
            'provider_id' => '999888777666555444',
        ]);

        Role::findOrCreate('member');
        $user->assignRole('member');

        DiscordRoleMapping::query()->create([
            'spatie_role_name' => 'member',
            'discord_role_id' => '444',
            'sync_to_discord' => true,
        ]);

        $this->signedPost('/discord/bot/members/roles', [
            'discord_user_id' => '999888777666555444',
        ])->assertOk()
            ->assertJsonPath('linked', true)
            ->assertJsonPath('spatie_roles.0', 'member')
            ->assertJsonPath('mapped_discord_roles.0', '444');
    }
}
