<?php

namespace MetaForge\Discord\Console\Commands;

use Illuminate\Console\Command;
use MetaForge\Discord\Services\DiscordApiClient;
use MetaForge\Discord\Services\DiscordApiException;

class DiscordPingCommand extends Command
{
    protected $signature = 'discord:ping';

    protected $description = 'Verify the Discord bot token by calling GET /users/@me';

    public function handle(DiscordApiClient $client): int
    {
        try {
            $user = $client->getCurrentUser();
        } catch (DiscordApiException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $username = $user['username'] ?? 'unknown';
        $id = $user['id'] ?? '?';

        $this->components->info("Discord bot authenticated as {$username} ({$id}).");

        return self::SUCCESS;
    }
}
