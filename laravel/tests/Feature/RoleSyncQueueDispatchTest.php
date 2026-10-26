<?php

namespace MetaForge\Discord\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use MetaForge\Discord\Jobs\SyncUserDiscordRolesJob;
use MetaForge\Discord\Services\RoleSyncService;
use MetaForge\Discord\Tests\TestCase;
use MetaForge\Discord\Tests\TestSocialAccount;
use MetaForge\Discord\Tests\TestUser;

class RoleSyncQueueDispatchTest extends TestCase
{
    public function test_dispatch_sync_jobs_for_linked_users_only(): void
    {
        Bus::fake([SyncUserDiscordRolesJob::class]);

        $linked = TestUser::query()->create(['name' => 'Linked', 'email' => 'l@example.test']);
        TestSocialAccount::query()->create([
            'user_id' => $linked->id,
            'provider' => 'discord',
            'provider_id' => '999',
        ]);

        TestUser::query()->create(['name' => 'Unlinked', 'email' => 'u@example.test']);

        $counts = app(RoleSyncService::class)->dispatchSyncJobsForLinkedUsers();

        $this->assertSame(1, $counts['queued']);
        Bus::assertDispatched(SyncUserDiscordRolesJob::class);
    }
}
