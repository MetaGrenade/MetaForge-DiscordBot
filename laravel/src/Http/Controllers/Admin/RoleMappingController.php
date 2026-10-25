<?php

namespace MetaForge\Discord\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MetaForge\Discord\Models\DiscordRoleMapping;

class RoleMappingController extends Controller
{
    public function index(): JsonResponse
    {
        $mappings = DiscordRoleMapping::query()
            ->orderBy('spatie_role_name')
            ->get();

        return response()->json(['data' => $mappings]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'spatie_role_name' => ['required', 'string', 'max:191'],
            'discord_role_id' => ['required', 'string', 'max:32'],
            'sync_to_discord' => ['sometimes', 'boolean'],
            'sync_from_discord' => ['sometimes', 'boolean'],
        ]);

        $mapping = DiscordRoleMapping::query()->create([
            'spatie_role_name' => $data['spatie_role_name'],
            'discord_role_id' => $data['discord_role_id'],
            'sync_to_discord' => $data['sync_to_discord'] ?? true,
            'sync_from_discord' => $data['sync_from_discord'] ?? false,
        ]);

        return response()->json(['data' => $mapping], 201);
    }

    public function update(Request $request, DiscordRoleMapping $roleMapping): JsonResponse
    {
        $data = $request->validate([
            'spatie_role_name' => ['sometimes', 'string', 'max:191'],
            'discord_role_id' => ['sometimes', 'string', 'max:32'],
            'sync_to_discord' => ['sometimes', 'boolean'],
            'sync_from_discord' => ['sometimes', 'boolean'],
        ]);

        $roleMapping->fill($data)->save();

        return response()->json(['data' => $roleMapping->fresh()]);
    }

    public function destroy(DiscordRoleMapping $roleMapping): JsonResponse
    {
        $roleMapping->delete();

        return response()->json(null, 204);
    }
}
