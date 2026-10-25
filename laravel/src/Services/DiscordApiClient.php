<?php

namespace MetaForge\Discord\Services;

use Illuminate\Http\Client\PendingRequest;
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

    private function request(): PendingRequest
    {
        if (! $this->botToken) {
            throw new DiscordApiException('DISCORD_BOT_TOKEN is not configured.');
        }

        return Http::baseUrl($this->apiBase)
            ->acceptJson()
            ->asJson()
            ->withToken($this->botToken, 'Bot')
            ->retry(3, 500, function ($exception, $request) {
                $response = $exception->response ?? null;

                return $response && $response->status() === 429;
            }, throw: false);
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
