<?php

namespace MetaForge\Discord\Listeners;

use MetaForge\Discord\Jobs\NotifyDiscordSupportTicketJob;

/**
 * Host MetaForge apps wire support ticket events in config/discord.php → support_ticket_event_classes.
 */
class QueueSupportTicketDiscordNotification
{
    public function handle(object $event): void
    {
        $ticketId = $this->resolveTicketId($event);
        if ($ticketId === null) {
            return;
        }

        $userId = $this->resolveUserId($event);
        $eventName = class_basename($event);

        NotifyDiscordSupportTicketJob::dispatch($eventName, $ticketId, $userId);
    }

    private function resolveTicketId(object $event): ?int
    {
        foreach (['ticketId', 'ticket_id', 'id'] as $property) {
            if (isset($event->{$property}) && is_numeric($event->{$property})) {
                return (int) $event->{$property};
            }
        }

        if (method_exists($event, 'ticket') && is_object($event->ticket())) {
            $ticket = $event->ticket();

            return isset($ticket->id) ? (int) $ticket->id : null;
        }

        return null;
    }

    private function resolveUserId(object $event): ?int
    {
        foreach (['userId', 'user_id'] as $property) {
            if (isset($event->{$property}) && is_numeric($event->{$property})) {
                return (int) $event->{$property};
            }
        }

        return null;
    }
}
