<?php

namespace MetaForge\Discord\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordGuild extends Model
{
    protected $fillable = [
        'guild_id',
        'label',
        'is_primary',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'enabled' => 'boolean',
        ];
    }
}
