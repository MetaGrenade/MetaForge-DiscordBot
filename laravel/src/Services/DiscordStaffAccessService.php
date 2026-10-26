<?php

namespace MetaForge\Discord\Services;

use Illuminate\Database\Eloquent\Model;

class DiscordStaffAccessService
{
    public function __construct(
        private readonly DiscordMemberLookupService $members,
    ) {}

    /**
     * @param  list<string>  $memberRoleIds  Discord role snowflakes on the guild member
     */
    public function canAccessSupportTools(string $discordUserId, array $memberRoleIds): bool
    {
        /** @var list<string> $staffRoleIds */
        $staffRoleIds = array_values(array_filter(
            array_map(strval(...), (array) config('discord.support_staff_discord_role_ids', [])),
        ));

        if ($staffRoleIds !== [] && array_intersect($staffRoleIds, $memberRoleIds) !== []) {
            return true;
        }

        $user = $this->findLinkedUser($discordUserId);
        if ($user === null) {
            return false;
        }

        /** @var list<string> $permissions */
        $permissions = array_values(array_filter(
            array_map(strval(...), (array) config('discord.support_staff_permissions', [])),
        ));

        if ($permissions === []) {
            return false;
        }

        if (method_exists($user, 'can')) {
            foreach ($permissions as $permission) {
                if ($user->can($permission)) {
                    return true;
                }
            }
        }

        if (method_exists($user, 'hasAnyRole')) {
            /** @var list<string> $roles */
            $roles = array_values(array_filter(
                array_map(strval(...), (array) config('discord.support_staff_spatie_roles', [])),
            ));

            if ($roles !== [] && $user->hasAnyRole($roles)) {
                return true;
            }
        }

        return false;
    }

    private function findLinkedUser(string $discordUserId): ?Model
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
