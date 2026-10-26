<?php

namespace MetaForge\Discord\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use MetaForge\Discord\Services\ForumCrosspostService;

class CrossPostForumItemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @param  array{title: string, url: string, excerpt?: string|null, board_slug?: string|null}  $item
     */
    public function __construct(
        public array $item,
        public string $sourceKey,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('discord-outbound')];
    }

    public function handle(ForumCrosspostService $crosspost): void
    {
        $crosspost->crosspost($this->item, $this->sourceKey);
    }
}
