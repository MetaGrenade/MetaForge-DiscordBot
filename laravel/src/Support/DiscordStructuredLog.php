<?php

namespace MetaForge\Discord\Support;

use Illuminate\Support\Facades\Log;

class DiscordStructuredLog
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $event, array $context = []): void
    {
        if (! config('discord.observability.structured_logging', true)) {
            return;
        }

        Log::info($event, array_merge(['discord_event' => $event], $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $event, array $context = []): void
    {
        if (! config('discord.observability.structured_logging', true)) {
            return;
        }

        Log::warning($event, array_merge(['discord_event' => $event], $context));
    }
}
