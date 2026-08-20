<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserPreferenceController extends Controller
{
    /**
     * Get current user's preferences.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => [
                    'in_app' => $user->getPreference('in_app', true),
                    'email' => $user->getPreference('email', true),
                ]
            ]
        ]);
    }

    /**
     * Update current user's preferences.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'notifications.in_app' => 'required|boolean',
            'notifications.email' => 'required|boolean',
        ]);

        $user = Auth::user();
        
        $preferences = $user->preferences ?? [];
        $preferences['in_app'] = $validated['notifications']['in_app'];
        $preferences['email'] = $validated['notifications']['email'];

        $user->preferences = $preferences;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully.',
            'data' => [
                'notifications' => [
                    'in_app' => $user->getPreference('in_app', true),
                    'email' => $user->getPreference('email', true),
                ]
            ]
        ]);
    }
}
