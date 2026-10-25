<?php

namespace MetaForge\Discord\Services;

use Illuminate\Database\Eloquent\Model;
use MetaForge\Discord\Events\UserDiscordNotificationPreferenceUpdated;
use MetaForge\Discord\Models\DiscordNotificationPreference;

class DiscordNotificationPreferenceService
{
    /**
     * @return array<string, bool>
     */
    public function preferencesForUser(Model $user): array
    {
        $categories = array_keys(config('discord.notification_categories', []));
        $stored = DiscordNotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->pluck('enabled', 'category');

        $result = [];
        foreach ($categories as $category) {
            $result[$category] = (bool) ($stored[$category] ?? false);
        }

        return $result;
    }

    public function isEnabled(Model $user, string $category): bool
    {
        if (! array_key_exists($category, config('discord.notification_categories', []))) {
            return false;
        }

        $preference = DiscordNotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->where('category', $category)
            ->first();

        return (bool) ($preference?->enabled ?? false);
    }

    /**
     * @param  array<string, bool>  $preferences
     */
    public function syncForUser(Model $user, array $preferences): void
    {
        $allowed = array_keys(config('discord.notification_categories', []));

        foreach ($preferences as $category => $enabled) {
            if (! in_array($category, $allowed, true)) {
                continue;
            }

            DiscordNotificationPreference::query()->updateOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'category' => $category,
                ],
                ['enabled' => (bool) $enabled],
            );

            UserDiscordNotificationPreferenceUpdated::dispatch(
                (int) $user->getKey(),
                $category,
                (bool) $enabled,
            );
        }
    }
}
