<?php

namespace MetaForge\Discord\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MetaForge\Discord\Services\DiscordNotificationPreferenceService;

class NotificationPreferenceController extends Controller
{
    public function show(Request $request, DiscordNotificationPreferenceService $preferences): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'categories' => config('discord.notification_categories', []),
            'preferences' => $preferences->preferencesForUser($user),
        ]);
    }

    public function update(Request $request, DiscordNotificationPreferenceService $preferences): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*' => ['boolean'],
        ]);

        $preferences->syncForUser($user, $data['preferences']);

        return response()->json([
            'preferences' => $preferences->preferencesForUser($user),
        ]);
    }
}
