<?php

namespace MetaForge\Discord\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordForumCrosspostLog extends Model
{
    protected $table = 'discord_forum_crosspost_log';

    protected $fillable = [
        'source_key',
        'message_id',
    ];
}
