<?php

namespace MetaForge\Discord\Services;

use MetaForge\Discord\Support\ModerationMirrorPayload;

class ModerationEventExtractor
{
    public function fromEvent(object $event): ?ModerationMirrorPayload
    {
        $action = $this->resolveAction($event);
        if ($action === null) {
            return null;
        }

        $actionId = $this->intFrom($action, 'id');
        $memberUserId = $this->intFrom($action, 'user_id');
        $type = $this->stringFrom($action, 'type');

        if ($actionId === null || $memberUserId === null || $type === null) {
            return null;
        }

        $type = strtolower($type);
        if (! in_array($type, ['warning', 'ban', 'unban', 'removal'], true)) {
            return null;
        }

        $actorUserId = $this->intFrom($action, 'actor_id');
        $subjectLabel = $this->sanitizeLabel($this->stringFrom($action, 'subject_label'));
        $reasonExcerpt = $this->resolveReasonExcerpt($action);
        $actionUrl = $this->resolveActionUrl($event, $action);

        return new ModerationMirrorPayload(
            actionId: $actionId,
            type: $type,
            memberUserId: $memberUserId,
            actorUserId: $actorUserId,
            subjectLabel: $subjectLabel,
            reasonExcerpt: $reasonExcerpt,
            actionUrl: $actionUrl,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function fromWebhook(array $payload): ?ModerationMirrorPayload
    {
        $actionId = isset($payload['action_id']) && is_numeric($payload['action_id'])
            ? (int) $payload['action_id']
            : null;

        $memberUserId = isset($payload['user_id']) && is_numeric($payload['user_id'])
            ? (int) $payload['user_id']
            : null;

        $type = isset($payload['type']) && is_string($payload['type'])
            ? strtolower($payload['type'])
            : null;

        if ($actionId === null || $memberUserId === null || $type === null) {
            return null;
        }

        if (! in_array($type, ['warning', 'ban', 'unban', 'removal'], true)) {
            return null;
        }

        $actorUserId = isset($payload['actor_id']) && is_numeric($payload['actor_id'])
            ? (int) $payload['actor_id']
            : null;

        $subjectLabel = isset($payload['subject_label']) && is_string($payload['subject_label'])
            ? $this->sanitizeLabel($payload['subject_label'])
            : null;

        $reasonExcerpt = isset($payload['reason_excerpt']) && is_string($payload['reason_excerpt'])
            ? $this->sanitizeReason($payload['reason_excerpt'])
            : null;

        $actionUrl = isset($payload['url']) && is_string($payload['url']) ? $payload['url'] : null;

        return new ModerationMirrorPayload(
            actionId: $actionId,
            type: $type,
            memberUserId: $memberUserId,
            actorUserId: $actorUserId,
            subjectLabel: $subjectLabel,
            reasonExcerpt: $reasonExcerpt,
            actionUrl: $actionUrl,
        );
    }

    private function resolveAction(object $event): ?object
    {
        foreach (['moderationAction', 'moderation_action'] as $method) {
            if (method_exists($event, $method)) {
                $action = $event->{$method}();
                if (is_object($action)) {
                    return $action;
                }
            }
        }

        foreach (['moderationAction', 'moderation_action', 'action'] as $property) {
            if (isset($event->{$property}) && is_object($event->{$property})) {
                return $event->{$property};
            }
        }

        if (isset($event->action_id) || isset($event->actionId)) {
            return (object) [
                'id' => $event->action_id ?? $event->actionId,
                'user_id' => $event->user_id ?? $event->userId ?? null,
                'actor_id' => $event->actor_id ?? $event->actorId ?? null,
                'type' => $event->type ?? null,
                'reason' => $event->reason ?? null,
                'subject_label' => $event->subject_label ?? $event->subjectLabel ?? null,
            ];
        }

        return null;
    }

    private function resolveReasonExcerpt(object $action): ?string
    {
        if (! config('discord.moderation_staff_reason_in_embed', false)) {
            return null;
        }

        $reason = $this->stringFrom($action, 'reason');
        if ($reason === null) {
            return null;
        }

        return $this->sanitizeReason($reason);
    }

    private function resolveActionUrl(object $event, object $action): ?string
    {
        foreach (['moderationActionUrl', 'actionUrl', 'moderationUrl'] as $method) {
            if (method_exists($event, $method)) {
                $url = $event->{$method}();
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }
        }

        $template = (string) config('discord.moderation_url_template', '');
        if ($template === '') {
            return null;
        }

        $replacements = [
            '{action_id}' => (string) ($action->id ?? ''),
            '{user_id}' => (string) ($action->user_id ?? ''),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function sanitizeReason(string $text): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');

        return mb_substr($plain, 0, (int) config('discord.moderation_reason_excerpt_max', 200));
    }

    private function sanitizeLabel(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($label)) ?? '');

        if ($plain === '') {
            return null;
        }

        return mb_substr($plain, 0, (int) config('discord.moderation_subject_label_max', 120));
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
