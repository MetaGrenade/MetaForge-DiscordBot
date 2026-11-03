<?php

namespace MetaForge\Discord\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use MetaForge\Discord\Jobs\SyncUserDiscordRolesJob;
use MetaForge\Discord\Models\DiscordSyncFailure;
use MetaForge\Discord\Services\DiscordSyncFailureService;
use MetaForge\Discord\Tests\TestCase;
use MetaForge\Discord\Tests\TestUser;

class DiscordSyncFailureTest extends TestCase
{
    public function test_retry_command_dispatches_outbound_job_and_resolves_failure(): void
    {
        Bus::fake([SyncUserDiscordRolesJob::class]);

        $user = TestUser::query()->create(['name' => 'Retry', 'email' => 'retry@example.test']);

        $failure = DiscordSyncFailure::record(
            DiscordSyncFailure::OPERATION_OUTBOUND_ROLE_SYNC,
            'Simulated failure',
            $user->id,
            null,
            '123456789012345678',
            ['user_id' => $user->id, 'user_class' => TestUser::class],
        );

        $counts = app(DiscordSyncFailureService::class)->retryUnresolved(10, false);

        $this->assertSame(1, $counts['retried']);
        Bus::assertDispatched(SyncUserDiscordRolesJob::class, function (SyncUserDiscordRolesJob $job) use ($user) {
            return $job->userId === $user->id && $job->userClass === TestUser::class;
        });
        $this->assertNotNull($failure->fresh()->resolved_at);
    }

    public function test_dry_run_does_not_resolve_failures(): void
    {
        Bus::fake([SyncUserDiscordRolesJob::class]);

        DiscordSyncFailure::record(
            DiscordSyncFailure::OPERATION_OUTBOUND_ROLE_SYNC,
            'Simulated failure',
            1,
            null,
            null,
            ['user_id' => 1, 'user_class' => TestUser::class],
        );

        $counts = app(DiscordSyncFailureService::class)->retryUnresolved(10, true);

        $this->assertSame(1, $counts['retried']);
        Bus::assertNothingDispatched();
        $this->assertNull(DiscordSyncFailure::query()->first()?->resolved_at);
    }
}
