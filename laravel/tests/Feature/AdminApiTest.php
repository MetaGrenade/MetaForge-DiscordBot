<?php

namespace MetaForge\Discord\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use MetaForge\Discord\Jobs\SyncUserDiscordRolesJob;
use MetaForge\Discord\Models\DiscordRoleMapping;
use MetaForge\Discord\Models\DiscordSyncFailure;
use MetaForge\Discord\Tests\TestCase;
use MetaForge\Discord\Tests\TestUser;

class AdminApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('discord.admin_middleware', []);
    }

    public function test_role_mapping_crud(): void
    {
        $this->postJson('/discord/admin/role-mappings', [
            'spatie_role_name' => 'vip',
            'discord_role_id' => '555',
        ])->assertCreated()
            ->assertJsonPath('data.spatie_role_name', 'vip');

        $mapping = DiscordRoleMapping::query()->firstOrFail();

        $this->getJson('/discord/admin/role-mappings')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->putJson('/discord/admin/role-mappings/'.$mapping->id, [
            'sync_to_discord' => false,
        ])->assertOk()
            ->assertJsonPath('data.sync_to_discord', false);

        $this->deleteJson('/discord/admin/role-mappings/'.$mapping->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('discord_role_mappings', ['id' => $mapping->id]);
    }

    public function test_sync_status_includes_unresolved_failure_count(): void
    {
        DiscordSyncFailure::record(
            DiscordSyncFailure::OPERATION_OUTBOUND_ROLE_SYNC,
            'Failed',
            1,
        );
        DiscordSyncFailure::record(
            DiscordSyncFailure::OPERATION_OUTBOUND_ROLE_SYNC,
            'Resolved',
            2,
        )->markResolved();

        $this->getJson('/discord/admin/sync/status')
            ->assertOk()
            ->assertJsonPath('unresolved_failures', 1);
    }

    public function test_sync_failures_list_and_unresolved_filter(): void
    {
        DiscordSyncFailure::record(
            DiscordSyncFailure::OPERATION_OUTBOUND_ROLE_SYNC,
            'Open',
            1,
        );
        DiscordSyncFailure::record(
            DiscordSyncFailure::OPERATION_INBOUND_ROLE_SYNC,
            'Closed',
            null,
            '999',
        )->markResolved();

        $this->getJson('/discord/admin/sync/failures')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/discord/admin/sync/failures?unresolved=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.error', 'Open');
    }

    public function test_sync_failures_retry_endpoint(): void
    {
        Bus::fake([SyncUserDiscordRolesJob::class]);

        $user = TestUser::query()->create(['name' => 'Api', 'email' => 'api-retry@example.test']);

        $failure = DiscordSyncFailure::record(
            DiscordSyncFailure::OPERATION_OUTBOUND_ROLE_SYNC,
            'Simulated failure',
            $user->id,
            null,
            '123456789012345678',
            ['user_id' => $user->id, 'user_class' => TestUser::class],
        );

        $this->postJson('/discord/admin/sync/failures/retry', [
            'ids' => [$failure->id],
        ])->assertOk()
            ->assertJson(['retried' => 1, 'skipped' => 0]);

        Bus::assertDispatched(SyncUserDiscordRolesJob::class);
        $this->assertNotNull($failure->fresh()->resolved_at);
    }
}
