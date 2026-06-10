<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserPreferenceController extends Controller
{
    /**
     * Update (or create) preferences for the authenticated user.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme_color' => ['sometimes', 'string', 'max:20'],
            'accent_hex'  => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dark_mode'   => ['sometimes', 'boolean'],
        ]);

        $preferences = UserPreference::updateOrCreate(
            ['user_id' => auth()->id()],
            $validated,
        );

        return response()->json([
            'message'     => 'Preferences saved.',
            'preferences' => $preferences->only(['theme_color', 'accent_hex', 'dark_mode']),
        ]);
    }
}
