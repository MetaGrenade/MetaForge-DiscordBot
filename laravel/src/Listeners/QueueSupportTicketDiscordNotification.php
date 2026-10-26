<?php

namespace MetaForge\Discord\Listeners;

use MetaForge\Discord\Jobs\NotifyDiscordSupportTicketJob;
use MetaForge\Discord\Services\SupportTicketEventExtractor;

/**
 * Host MetaForge apps wire support ticket events in config/discord.php → support_ticket_event_classes.
 */
class QueueSupportTicketDiscordNotification
{
    public function __construct(
        private readonly SupportTicketEventExtractor $extractor,
    ) {}

    public function handle(object $event): void
    {
        $payload = $this->extractor->fromEvent($event);
        if ($payload === null) {
            return;
        }

        NotifyDiscordSupportTicketJob::dispatch($payload);
    }
}
