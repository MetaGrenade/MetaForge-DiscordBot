<?php

namespace MetaForge\Discord\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Suppresses app → Discord sync jobs while applying Discord → app role changes (avoids feedback loops).
 */
class OutboundDiscordRoleSyncGuard
{
    private const CACHE_PREFIX = 'discord:suppress_outbound_sync:';

    public static function suppress(int $userId, int $seconds = 30): void
    {
        Cache::put(self::CACHE_PREFIX.$userId, true, $seconds);
    }

    public static function isSuppressed(int $userId): bool
    {
        return Cache::get(self::CACHE_PREFIX.$userId) === true;
    }

    public static function clear(int $userId): void
    {
        Cache::forget(self::CACHE_PREFIX.$userId);
    }
}
