<?php

namespace MetaForge\Discord\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class DiscordApiClient
{
    public function __construct(
        private readonly ?string $botToken,
        private readonly string $apiBase,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('discord.bot_token'),
            (string) config('discord.api_base'),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getGuildMember(string $guildId, string $userId): ?array
    {
        $response = $this->request()->get("/guilds/{$guildId}/members/{$userId}");

        if ($response->status() === 404) {
            return null;
        }

        return $this->decode($response);
    }

    /**
     * @param  list<string>  $roleIds
     * @return array<string, mixed>
     */
    public function modifyGuildMemberRoles(string $guildId, string $userId, array $roleIds): array
    {
        $response = $this->request()->patch("/guilds/{$guildId}/members/{$userId}", [
            'roles' => array_values(array_unique($roleIds)),
        ]);

        return $this->decode($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCurrentUser(): array
    {
        $response = $this->request()->get('/users/@me');

        return $this->decode($response);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listGuildRoles(string $guildId): array
    {
        $response = $this->request()->get("/guilds/{$guildId}/roles");
        /** @var list<array<string, mixed>> $roles */
        $roles = $this->decode($response);

        return $roles;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createMessage(string $channelId, array $payload): array
    {
        $response = $this->request()->post("/channels/{$channelId}/messages", $payload);

        return $this->decode($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function startThreadFromMessage(
        string $channelId,
        string $messageId,
        string $name,
        int $autoArchiveDurationMinutes = 1440,
    ): array {
        $response = $this->request()->post("/channels/{$channelId}/messages/{$messageId}/threads", [
            'name' => $name,
            'auto_archive_duration' => $autoArchiveDurationMinutes,
        ]);

        return $this->decode($response);
    }

    private function request(): PendingRequest
    {
        if (! $this->botToken) {
            throw new DiscordApiException('DISCORD_BOT_TOKEN is not configured.');
        }

        return Http::baseUrl($this->apiBase)
            ->acceptJson()
            ->asJson()
            ->withToken($this->botToken, 'Bot')
            ->retry(
                times: (int) config('discord.api_retry_times', 5),
                sleepMilliseconds: function (int $attempt, \Throwable $exception) {
                    if ($exception instanceof RequestException) {
                        $response = $exception->response;

                        if ($response->status() === 429) {
                            $retryAfter = $response->header('Retry-After');
                            $seconds = is_numeric($retryAfter) ? (int) $retryAfter : min(30, $attempt * 2);

                            return max(1000, $seconds * 1000);
                        }
                    }

                    return min(8000, 500 * $attempt);
                },
                when: fn (\Throwable $exception) => $exception instanceof RequestException
                    && $exception->response->status() === 429,
                throw: false,
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        if ($response->successful()) {
            /** @var array<string, mixed> $json */
            $json = $response->json() ?? [];

            return $json;
        }

        throw new DiscordApiException(
            'Discord API request failed: '.$response->body(),
            $response->status(),
            $response->json(),
        );
    }
}
