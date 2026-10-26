<?php

namespace MetaForge\Discord\Services;

use MetaForge\Discord\Support\SupportTicketMirrorPayload;

class SupportTicketEventExtractor
{
    public function fromEvent(object $event): ?SupportTicketMirrorPayload
    {
        $ticket = $this->resolveTicket($event);
        if ($ticket === null) {
            return null;
        }

        $ticketId = $this->intFrom($ticket, 'id');
        if ($ticketId === null) {
            return null;
        }

        $subject = $this->stringFrom($ticket, 'subject') ?? 'Support ticket #'.$ticketId;
        $status = $this->stringFrom($ticket, 'status') ?? 'unknown';
        $userId = $this->intFrom($ticket, 'user_id');

        $kind = $this->resolveKind($event);
        $excerpt = $this->resolveExcerpt($event);
        $url = $this->resolveTicketUrl($event, $ticket);

        return new SupportTicketMirrorPayload(
            ticketId: $ticketId,
            kind: $kind,
            subject: $subject,
            status: $status,
            ticketUrl: $url,
            excerpt: $excerpt,
            userId: $userId,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function fromWebhook(array $payload): ?SupportTicketMirrorPayload
    {
        $ticketId = isset($payload['ticket_id']) && is_numeric($payload['ticket_id'])
            ? (int) $payload['ticket_id']
            : null;

        if ($ticketId === null) {
            return null;
        }

        $kind = isset($payload['kind']) && is_string($payload['kind'])
            ? $payload['kind']
            : 'updated';

        if (! in_array($kind, ['opened', 'reply', 'updated', 'closed'], true)) {
            $kind = 'updated';
        }

        $subject = isset($payload['subject']) && is_string($payload['subject'])
            ? $payload['subject']
            : 'Support ticket #'.$ticketId;

        $status = isset($payload['status']) && is_string($payload['status'])
            ? $payload['status']
            : 'unknown';

        $url = isset($payload['url']) && is_string($payload['url']) ? $payload['url'] : null;
        $excerpt = isset($payload['excerpt']) && is_string($payload['excerpt'])
            ? $this->sanitizeExcerpt($payload['excerpt'])
            : null;

        $userId = isset($payload['user_id']) && is_numeric($payload['user_id'])
            ? (int) $payload['user_id']
            : null;

        return new SupportTicketMirrorPayload(
            ticketId: $ticketId,
            kind: $kind,
            subject: $subject,
            status: $status,
            ticketUrl: $url,
            excerpt: $excerpt,
            userId: $userId,
        );
    }

    private function resolveKind(object $event): string
    {
        foreach (['kind', 'activity', 'action'] as $property) {
            if (isset($event->{$property}) && is_string($event->{$property})) {
                $value = strtolower($event->{$property});
                if (in_array($value, ['opened', 'reply', 'updated', 'closed'], true)) {
                    return $value;
                }
            }
        }

        $name = strtolower(class_basename($event));

        if (str_contains($name, 'reply') || str_contains($name, 'replied')) {
            return 'reply';
        }

        if (str_contains($name, 'open') || str_contains($name, 'created')) {
            return 'opened';
        }

        if (str_contains($name, 'close') || str_contains($name, 'resolved')) {
            return 'closed';
        }

        return 'updated';
    }

    private function resolveExcerpt(object $event): ?string
    {
        foreach (['excerpt', 'body', 'message_body'] as $property) {
            if (isset($event->{$property}) && is_string($event->{$property})) {
                return $this->sanitizeExcerpt($event->{$property});
            }
        }

        if (method_exists($event, 'message')) {
            $message = $event->message();
            if (is_object($message) && isset($message->body) && is_string($message->body)) {
                return $this->sanitizeExcerpt($message->body);
            }
        }

        return null;
    }

    private function resolveTicketUrl(object $event, object $ticket): ?string
    {
        if (method_exists($event, 'ticketUrl')) {
            $url = $event->ticketUrl();
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        $template = (string) config('discord.support_ticket_url_template', '');
        if ($template !== '') {
            return str_replace('{id}', (string) ($ticket->id ?? ''), $template);
        }

        return null;
    }

    private function resolveTicket(object $event): ?object
    {
        if (method_exists($event, 'ticket')) {
            $ticket = $event->ticket();
            if (is_object($ticket)) {
                return $ticket;
            }
        }

        foreach (['ticket', 'supportTicket'] as $property) {
            if (isset($event->{$property}) && is_object($event->{$property})) {
                return $event->{$property};
            }
        }

        if (isset($event->ticket_id) || isset($event->ticketId)) {
            return (object) [
                'id' => $event->ticket_id ?? $event->ticketId,
                'subject' => $event->subject ?? null,
                'status' => $event->status ?? null,
                'user_id' => $event->user_id ?? $event->userId ?? null,
            ];
        }

        return null;
    }

    private function sanitizeExcerpt(string $text): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');

        return mb_substr($plain, 0, (int) config('discord.support_ticket_excerpt_max', 280));
    }

    private function intFrom(object $object, string $property): ?int
    {
        if (! isset($object->{$property})) {
            return null;
        }

        return is_numeric($object->{$property}) ? (int) $object->{$property} : null;
    }

    private function stringFrom(object $object, string $property): ?string
    {
        if (! isset($object->{$property}) || ! is_string($object->{$property})) {
            return null;
        }

        $value = trim($object->{$property});

        return $value === '' ? null : $value;
    }
}
