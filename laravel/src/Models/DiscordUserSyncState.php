<?php

namespace MetaForge\Discord\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordUserSyncState extends Model
{
    protected $fillable = [
        'user_id',
        'last_status',
        'last_message',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }
}
