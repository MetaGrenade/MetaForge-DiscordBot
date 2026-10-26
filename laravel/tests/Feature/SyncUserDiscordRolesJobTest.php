<?php

namespace MetaForge\Discord\Tests\Feature;

use MetaForge\Discord\Jobs\SyncUserDiscordRolesJob;
use MetaForge\Discord\Tests\TestCase;
use MetaForge\Discord\Tests\TestUser;

class SyncUserDiscordRolesJobTest extends TestCase
{
    public function test_job_has_stable_unique_id_per_user(): void
    {
        $user = TestUser::query()->create(['name' => 'Job', 'email' => 'job@example.test']);

        $job = new SyncUserDiscordRolesJob($user->id, TestUser::class);

        $this->assertSame('discord-role-sync:'.TestUser::class.':'.$user->id, $job->uniqueId());
        $this->assertNotEmpty($job->middleware());
    }
}
