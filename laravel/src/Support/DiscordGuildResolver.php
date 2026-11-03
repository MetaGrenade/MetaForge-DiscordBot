<?php

namespace MetaForge\Discord\Support;

use MetaForge\Discord\Models\DiscordGuild;

class DiscordGuildResolver
{
    public function primaryGuildId(): ?string
    {
        $configured = (string) config('discord.guild_id');
        if ($configured !== '') {
            return $configured;
        }

        $primary = DiscordGuild::query()
            ->where('enabled', true)
            ->where('is_primary', true)
            ->value('guild_id');

        return is_string($primary) && $primary !== '' ? $primary : null;
    }

    public function isManagedGuild(string $guildId): bool
    {
        if ($guildId === '') {
            return false;
        }

        $registered = DiscordGuild::query()
            ->where('enabled', true)
            ->pluck('guild_id');

        if ($registered->isEmpty()) {
            $configured = (string) config('discord.guild_id');

            return $configured === '' || $guildId === $configured;
        }

        return $registered->contains($guildId);
    }
}
