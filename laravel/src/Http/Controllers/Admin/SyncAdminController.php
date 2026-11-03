<?php

namespace MetaForge\Discord\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MetaForge\Discord\Jobs\SyncUserDiscordRolesJob;
use MetaForge\Discord\Models\DiscordSyncFailure;
use MetaForge\Discord\Models\DiscordSyncRun;
use MetaForge\Discord\Services\DiscordApiClient;
use MetaForge\Discord\Services\DiscordApiException;
use MetaForge\Discord\Services\DiscordSyncFailureService;
use MetaForge\Discord\Services\RoleSyncService;

class SyncAdminController extends Controller
{
    public function status(RoleSyncService $sync): JsonResponse
    {
        $latestRun = DiscordSyncRun::query()->latest('id')->first();
        $running = DiscordSyncRun::query()
            ->where('status', DiscordSyncRun::STATUS_RUNNING)
            ->exists();

        return response()->json([
            'running' => $running,
            'stale_linked_users' => $sync->staleLinkedUserIds()->count(),
            'unresolved_failures' => DiscordSyncFailure::query()->whereNull('resolved_at')->count(),
            'latest_run' => $latestRun,
        ]);
    }

    public function runs(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $runs = DiscordSyncRun::query()
            ->latest('id')
            ->paginate($perPage);

        return response()->json($runs);
    }

    public function failures(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $query = DiscordSyncFailure::query()->latest('id');

        if ($request->boolean('unresolved')) {
            $query->whereNull('resolved_at');
        }

        return response()->json($query->paginate($perPage));
    }

    public function retryFailures(Request $request, DiscordSyncFailureService $service): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        /** @var list<int>|null $ids */
        $ids = isset($validated['ids']) ? array_values($validated['ids']) : null;
        $limit = (int) ($validated['limit'] ?? 50);
        $dryRun = (bool) ($validated['dry_run'] ?? false);

        $counts = $service->retryUnresolved($limit, $dryRun, $ids);

        return response()->json($counts);
    }

    public function syncUser(Request $request, int $userId, RoleSyncService $sync): JsonResponse
    {
        $userClass = config('discord.user_model');
        /** @var Model|null $user */
        $user = $userClass::query()->find($userId);

        if ($user === null) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $runSync = (bool) $request->boolean('sync', false);
        $dryRun = (bool) $request->boolean('dry_run', false);

        if ($runSync || $dryRun) {
            try {
                $result = $sync->syncUser($user, $dryRun);
            } catch (DiscordApiException $exception) {
                return response()->json(['message' => $exception->getMessage()], 502);
            }

            return response()->json([
                'status' => $result->status,
                'message' => $result->message,
            ]);
        }

        SyncUserDiscordRolesJob::dispatchForUser($user);

        return response()->json(['queued' => true, 'user_id' => $userId]);
    }

    public function discordRoles(DiscordApiClient $client): JsonResponse
    {
        $guildId = (string) config('discord.guild_id');

        if ($guildId === '') {
            return response()->json(['message' => 'DISCORD_GUILD_ID is not configured.'], 422);
        }

        try {
            $roles = $client->listGuildRoles($guildId);
        } catch (DiscordApiException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json(['data' => $roles]);
    }
}
