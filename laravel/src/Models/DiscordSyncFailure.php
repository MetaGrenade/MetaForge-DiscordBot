<?php

namespace MetaForge\Discord\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordSyncFailure extends Model
{
    public const OPERATION_OUTBOUND_ROLE_SYNC = 'outbound_role_sync';

    public const OPERATION_INBOUND_ROLE_SYNC = 'inbound_role_sync';

    protected $fillable = [
        'operation',
        'user_id',
        'discord_user_id',
        'guild_id',
        'payload',
        'error',
        'attempts',
        'last_failed_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'last_failed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function record(
        string $operation,
        string $error,
        ?int $userId = null,
        ?string $discordUserId = null,
        ?string $guildId = null,
        array $payload = [],
    ): self {
        return self::query()->create([
            'operation' => $operation,
            'user_id' => $userId,
            'discord_user_id' => $discordUserId,
            'guild_id' => $guildId,
            'payload' => $payload === [] ? null : $payload,
            'error' => $error,
            'attempts' => 1,
            'last_failed_at' => now(),
        ]);
    }

    public function markResolved(): void
    {
        $this->forceFill(['resolved_at' => now()])->save();
    }
}
