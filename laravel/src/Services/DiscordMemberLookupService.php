<?php

namespace MetaForge\Discord\Services;

use Illuminate\Database\Eloquent\Model;
use MetaForge\Discord\Models\DiscordRoleMapping;

class DiscordMemberLookupService
{
    /**
     * @return array{linked: bool, user?: array{id: int, name: string, email?: string}, discord_user_id: string}
     */
    public function linkStatus(string $discordUserId): array
    {
        $user = $this->findUserByDiscordId($discordUserId);

        if ($user === null) {
            return [
                'linked' => false,
                'discord_user_id' => $discordUserId,
            ];
        }

        return [
            'linked' => true,
            'discord_user_id' => $discordUserId,
            'user' => [
                'id' => (int) $user->getKey(),
                'name' => (string) ($user->name ?? $user->getAttribute('name') ?? ''),
                'email' => $user->getAttribute('email') !== null ? (string) $user->email : null,
            ],
        ];
    }

    /**
     * @return array{linked: bool, discord_user_id: string, spatie_roles?: list<string>, mapped_discord_roles?: list<string>}
     */
    public function rolesForDiscordMember(string $discordUserId): array
    {
        $user = $this->findUserByDiscordId($discordUserId);

        if ($user === null) {
            return [
                'linked' => false,
                'discord_user_id' => $discordUserId,
            ];
        }

        $spatieRoles = method_exists($user, 'getRoleNames')
            ? $user->getRoleNames()->values()->all()
            : [];

        $mappedDiscordRoles = DiscordRoleMapping::query()
            ->where('sync_to_discord', true)
            ->whereIn('spatie_role_name', $spatieRoles)
            ->pluck('discord_role_id')
            ->values()
            ->all();

        return [
            'linked' => true,
            'discord_user_id' => $discordUserId,
            'spatie_roles' => $spatieRoles,
            'mapped_discord_roles' => $mappedDiscordRoles,
        ];
    }

    private function findUserByDiscordId(string $discordUserId): ?Model
    {
        $socialClass = config('discord.social_account_model');
        $provider = (string) config('discord.social_provider');
        $userClass = config('discord.user_model');

        $account = $socialClass::query()
            ->where('provider', $provider)
            ->where('provider_id', $discordUserId)
            ->first();

        if ($account === null) {
            return null;
        }

        return $userClass::query()->find($account->user_id);
    }
}
