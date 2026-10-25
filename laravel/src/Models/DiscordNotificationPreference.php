<?php

namespace MetaForge\Discord\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordNotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
