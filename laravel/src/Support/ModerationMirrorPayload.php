<?php

namespace MetaForge\Discord\Support;

/**
 * Normalized moderation mirror payload for staff Discord channels (IDs and action metadata only).
 */
final class ModerationMirrorPayload
{
    /**
     * @param  'warning'|'ban'|'unban'|'removal'  $type
     */
    public function __construct(
        public int $actionId,
        public string $type,
        public int $memberUserId,
        public ?int $actorUserId = null,
        public ?string $subjectLabel = null,
        public ?string $reasonExcerpt = null,
        public ?string $actionUrl = null,
    ) {}
}
