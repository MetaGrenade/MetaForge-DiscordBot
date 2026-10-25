<?php

namespace MetaForge\Discord\Tests\Feature;

use MetaForge\Discord\Models\DiscordRoleMapping;
use MetaForge\Discord\Tests\TestCase;

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
}
