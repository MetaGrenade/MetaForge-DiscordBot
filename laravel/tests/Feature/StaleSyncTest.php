<?php

namespace MetaForge\Discord\Tests\Feature;

use Illuminate\Support\Facades\Http;
use MetaForge\Discord\Models\DiscordRoleMapping;
use MetaForge\Discord\Models\DiscordUserSyncState;
use MetaForge\Discord\Services\RoleSyncService;
use MetaForge\Discord\Tests\TestCase;
use MetaForge\Discord\Tests\TestSocialAccount;
use MetaForge\Discord\Tests\TestUser;
use Spatie\Permission\Models\Role;

class StaleSyncTest extends TestCase
{
    public function test_stale_sync_includes_never_synced_linked_users(): void
    {
        config()->set('discord.bot_token', 'test-token');
        config()->set('discord.stale_sync_hours', 24);

        Http::fake([
            'https://discord.com/api/v10/*' => Http::sequence()
                ->push(['roles' => []], 200)
                ->push(['user' => ['id' => '1']], 200),
        ]);

        $user = TestUser::query()->create(['name' => 'G', 'email' => 'g@example.test']);
        TestSocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'discord',
            'provider_id' => '123123123123123123',
        ]);

        Role::findOrCreate('member');
        $user->assignRole('member');

        DiscordRoleMapping::query()->create([
            'spatie_role_name' => 'member',
            'discord_role_id' => '321',
            'sync_to_discord' => true,
        ]);

        $sync = app(RoleSyncService::class);
        $this->assertSame(1, $sync->staleLinkedUserIds()->count());

        $counts = $sync->syncStaleUsers();
        $this->assertSame(1, $counts['processed']);
        $this->assertSame(1, $counts['synced']);

        $this->assertDatabaseHas('discord_user_sync_states', [
            'user_id' => $user->id,
            'last_status' => 'synced',
        ]);

        DiscordUserSyncState::query()->where('user_id', $user->id)->update([
            'last_synced_at' => now()->subHours(48),
        ]);

        $this->assertSame(1, $sync->staleLinkedUserIds()->count());
    }
}
