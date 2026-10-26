<?php

namespace MetaForge\Discord\Listeners;

use Illuminate\Database\Eloquent\Model;
use MetaForge\Discord\Jobs\SyncUserDiscordRolesJob;
use MetaForge\Discord\Support\OutboundDiscordRoleSyncGuard;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;

class QueueDiscordRoleSync
{
    public function handleRoleAttached(RoleAttached $event): void
    {
        $this->queueFor($event->model);
    }

    public function handleRoleDetached(RoleDetached $event): void
    {
        $this->queueFor($event->model);
    }

    private function queueFor(mixed $model): void
    {
        if (! $model instanceof Model) {
            return;
        }

        if ($model::class !== config('discord.user_model')) {
            return;
        }

        if (OutboundDiscordRoleSyncGuard::isSuppressed((int) $model->getKey())) {
            return;
        }

        SyncUserDiscordRolesJob::dispatchForUser($model);
    }
}
