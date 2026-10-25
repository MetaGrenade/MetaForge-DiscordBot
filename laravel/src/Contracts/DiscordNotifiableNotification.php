<?php

namespace MetaForge\Discord\Contracts;

/**
 * Implement on Laravel notifications that should respect Discord opt-in preferences.
 */
interface DiscordNotifiableNotification
{
    /**
     * Preference category key from config('discord.notification_categories').
     */
    public function discordNotificationCategory(): string;
}
