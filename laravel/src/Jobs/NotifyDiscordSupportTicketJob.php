<?php

namespace MetaForge\Discord\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use MetaForge\Discord\Services\SupportTicketMirrorService;
use MetaForge\Discord\Support\SupportTicketMirrorPayload;

class NotifyDiscordSupportTicketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public SupportTicketMirrorPayload $payload,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('discord-outbound')];
    }

    public function handle(SupportTicketMirrorService $mirror): void
    {
        $mirror->mirror($this->payload);
    }
}
