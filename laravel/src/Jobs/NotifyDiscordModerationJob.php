<?php

namespace MetaForge\Discord\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use MetaForge\Discord\Services\ModerationMirrorService;
use MetaForge\Discord\Support\ModerationMirrorPayload;

class NotifyDiscordModerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public ModerationMirrorPayload $payload,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('discord-outbound')];
    }

    public function handle(ModerationMirrorService $mirror): void
    {
        $mirror->mirror($this->payload);
    }
}
