<?php

namespace MetaForge\Discord\Tests\Feature;

use Illuminate\Support\Facades\Http;
use MetaForge\Discord\Models\DiscordRoleMapping;
use MetaForge\Discord\Services\RoleSyncService;
use MetaForge\Discord\Tests\TestCase;
use MetaForge\Discord\Tests\TestSocialAccount;
use MetaForge\Discord\Tests\TestUser;
use Spatie\Permission\Models\Role;

class RoleSyncServiceTest extends TestCase
{
    public function test_skips_user_without_discord_account(): void
    {
        $user = TestUser::query()->create(['name' => 'A', 'email' => 'a@example.test']);

        $result = app(RoleSyncService::class)->syncUser($user);

        $this->assertSame('skipped', $result->status);
    }

    public function test_syncs_mapped_roles_to_discord(): void
    {
        config()->set('discord.bot_token', 'test-token');

        Http::fake([
            'https://discord.com/api/v10/*' => Http::sequence()
                ->push(['roles' => ['111', '999']], 200)
                ->push(['user' => ['id' => '1']], 200),
        ]);

        $user = TestUser::query()->create(['name' => 'B', 'email' => 'b@example.test']);
        TestSocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'discord',
            'provider_id' => '987654321098765432',
        ]);

        Role::findOrCreate('member');
        $user->assignRole('member');

        DiscordRoleMapping::query()->create([
            'spatie_role_name' => 'member',
            'discord_role_id' => '222',
            'sync_to_discord' => true,
        ]);

        DiscordRoleMapping::query()->create([
            'spatie_role_name' => 'staff',
            'discord_role_id' => '111',
            'sync_to_discord' => true,
        ]);

        $result = app(RoleSyncService::class)->syncUser($user);

        $this->assertSame('synced', $result->status);

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), '/guilds/123456789012345678/members/987654321098765432')
                && $request['roles'] === ['999', '222'];
        });
    }
}
