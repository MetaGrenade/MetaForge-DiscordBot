<?php

namespace MetaForge\Discord\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin stub: posts a short support-ticket notice to the default Discord webhook when configured.
 * Future: thread mirroring via gateway bot events (see docs/support-tickets.md).
 */
class NotifyDiscordSupportTicketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public string $eventName,
        public int $ticketId,
        public ?int $userId = null,
    ) {}

    public function handle(): void
    {
        $webhookUrl = (string) config('discord.default_webhook_url');

        if ($webhookUrl === '') {
            Log::debug('Skipping Discord support ticket notification — DISCORD_WEBHOOK_URL not set.', [
                'event' => $this->eventName,
                'ticket_id' => $this->ticketId,
            ]);

            return;
        }

        $content = sprintf(
            '[MetaForge] Support ticket event `%s` (ticket #%d%s)',
            $this->eventName,
            $this->ticketId,
            $this->userId !== null ? ', user '.$this->userId : '',
        );

        Http::asJson()->post($webhookUrl, [
            'content' => $content,
        ])->throw();
    }
}
