<?php

namespace MetaForge\Discord\Tests\Feature;

use MetaForge\Discord\Models\DiscordGuild;
use MetaForge\Discord\Support\DiscordGuildResolver;
use MetaForge\Discord\Tests\TestCase;

class DiscordGuildRegistryTest extends TestCase
{
    public function test_primary_guild_falls_back_to_env(): void
    {
        config()->set('discord.guild_id', '111111111111111111');

        $this->assertSame('111111111111111111', app(DiscordGuildResolver::class)->primaryGuildId());
    }

    public function test_managed_guild_uses_registry_when_populated(): void
    {
        config()->set('discord.guild_id', '111111111111111111');

        DiscordGuild::query()->create([
            'guild_id' => '222222222222222222',
            'label' => 'Partner server',
            'is_primary' => false,
            'enabled' => true,
        ]);

        $resolver = app(DiscordGuildResolver::class);

        $this->assertTrue($resolver->isManagedGuild('222222222222222222'));
        $this->assertFalse($resolver->isManagedGuild('333333333333333333'));
    }

    public function test_empty_registry_matches_configured_guild_only(): void
    {
        config()->set('discord.guild_id', '444444444444444444');

        $resolver = app(DiscordGuildResolver::class);

        $this->assertTrue($resolver->isManagedGuild('444444444444444444'));
        $this->assertFalse($resolver->isManagedGuild('555555555555555555'));
    }
}
