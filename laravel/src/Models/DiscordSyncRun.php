<?php

namespace MetaForge\Discord\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordSyncRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_OK = 'ok';

    public const STATUS_FAILED = 'failed';

    public const TRIGGER_COMMAND = 'command';

    public const TRIGGER_LISTENER = 'listener';

    public const TRIGGER_BOT = 'bot';

    public const TRIGGER_SCHEDULE = 'schedule';

    protected $fillable = [
        'trigger',
        'status',
        'users_processed',
        'users_synced',
        'users_skipped',
        'users_failed',
        'meta',
        'error',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'finished_at' => 'datetime',
        ];
    }
}
