<?php

namespace MetaForge\Discord\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordRoleMapping extends Model
{
    protected $fillable = [
        'spatie_role_name',
        'discord_role_id',
        'sync_to_discord',
        'sync_from_discord',
    ];

    protected function casts(): array
    {
        return [
            'sync_to_discord' => 'boolean',
            'sync_from_discord' => 'boolean',
        ];
    }
}
