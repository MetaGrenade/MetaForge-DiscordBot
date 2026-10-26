<?php

namespace MetaForge\Discord\Support;

/**
 * Normalized support-ticket mirror payload (no PII beyond public ticket metadata).
 */
final class SupportTicketMirrorPayload
{
    /**
     * @param  'opened'|'reply'|'updated'|'closed'  $kind
     */
    public function __construct(
        public int $ticketId,
        public string $kind,
        public string $subject,
        public string $status,
        public ?string $ticketUrl = null,
        public ?string $excerpt = null,
        public ?int $userId = null,
    ) {}
}
