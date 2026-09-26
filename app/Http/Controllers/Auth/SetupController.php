<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Organization;
use App\Models\Team;
use App\Models\Role;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SetupController extends Controller
{
    public function status(): JsonResponse
    {
        $setupRequired = true;
        
        if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
            $setupRequired = User::count() === 0;
        }

        return response()->json([
            'setup_required' => $setupRequired
        ]);
    }

    public function setup(Request $request): JsonResponse
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('users') && User::count() > 0) {
            return response()->json(['message' => 'Setup has already been completed.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        try {
            DB::beginTransaction();

            $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role_id' => $adminRole->id,
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ]);

            $org = Organization::firstOrCreate(
                ['slug' => 'default-organization'],
                ['name' => 'Default Organization']
            );

            $team = Team::firstOrCreate(
                ['slug' => 'default-team', 'organization_id' => $org->id],
                ['name' => 'Default Team']
            );

            $user->teams()->attach($team->id);

            DB::commit();

            return response()->json(['message' => 'Setup completed successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to initialize setup.', 'error' => $e->getMessage()], 500);
        }
    }
}
