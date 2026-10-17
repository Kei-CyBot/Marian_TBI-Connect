<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        return view('team', [
            'team' => User::query()->orderBy('name')->get(['id', 'name', 'email', 'role']),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(User::ROLES)],
        ]);

        DB::transaction(function () use ($request, $user, $validated): void {
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            $superAdminCount = User::query()
                ->where('role', User::ROLE_SUPER_ADMIN)
                ->lockForUpdate()
                ->count();
            abort_if(
                $request->user()->is($target)
                    && $target->isSuperAdmin()
                    && $validated['role'] !== User::ROLE_SUPER_ADMIN
                    && $superAdminCount < 2,
                422,
                'Assign another Super Admin before changing your own role.'
            );

            $target->forceFill(['role' => $validated['role']])->save();
        });

        $user->refresh();

        return to_route('team')->with('team_status', $user->name.' is now '.$user->roleLabel().'.');
    }
}
