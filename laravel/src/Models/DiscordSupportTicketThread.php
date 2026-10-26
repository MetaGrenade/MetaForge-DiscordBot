<?php

namespace MetaForge\Discord\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordSupportTicketThread extends Model
{
    protected $fillable = [
        'ticket_id',
        'channel_id',
        'thread_id',
        'starter_message_id',
        'last_event_type',
    ];

    protected $casts = [
        'ticket_id' => 'integer',
    ];
}
