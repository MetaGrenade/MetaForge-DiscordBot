<?php

namespace MetaForge\Discord\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserDiscordNotificationPreferenceUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $category,
        public readonly bool $enabled,
    ) {}
}
