<?php

namespace MetaForge\Discord\Listeners;

use MetaForge\Discord\Jobs\NotifyDiscordModerationJob;
use MetaForge\Discord\Services\ModerationEventExtractor;

/**
 * Host MetaForge apps wire moderation events in config/discord.php → moderation_event_classes.
 */
class QueueModerationDiscordNotification
{
    public function __construct(
        private readonly ModerationEventExtractor $extractor,
    ) {}

    public function handle(object $event): void
    {
        $payload = $this->extractor->fromEvent($event);
        if ($payload === null) {
            return;
        }

        NotifyDiscordModerationJob::dispatch($payload);
    }
}
