<?php

namespace MetaForge\Discord\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MetaForge\Discord\Services\DiscordMemberLookupService;

class BotMemberController
{
    public function linkStatus(Request $request, DiscordMemberLookupService $lookup): JsonResponse
    {
        $data = $request->validate([
            'discord_user_id' => ['required', 'string', 'max:32'],
        ]);

        return response()->json($lookup->linkStatus($data['discord_user_id']));
    }

    public function roles(Request $request, DiscordMemberLookupService $lookup): JsonResponse
    {
        $data = $request->validate([
            'discord_user_id' => ['required', 'string', 'max:32'],
        ]);

        return response()->json($lookup->rolesForDiscordMember($data['discord_user_id']));
    }
}
