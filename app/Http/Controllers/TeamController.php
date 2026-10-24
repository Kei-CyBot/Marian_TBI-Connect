<?php

namespace App\Http\Controllers;

use App\Mail\TeamInviteMail;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        return view('team', [
            'team' => User::query()->orderBy('name')->get(['id', 'name', 'email', 'role']),
            'invitations' => TeamInvitation::query()
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->orderBy('email')
                ->get(),
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

    public function invite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(User::ROLES)],
        ]);

        $invitation = TeamInvitation::create([
            'email' => $validated['email'],
            'role' => $validated['role'],
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'invited_by' => $request->user()->id,
        ]);

        Mail::to($invitation->email)->queue(new TeamInviteMail($invitation));

        return to_route('team')->with('team_status', 'Invitation sent to '.$invitation->email.'.');
    }

    public function revokeInvite(TeamInvitation $invitation): RedirectResponse
    {
        abort_if($invitation->accepted_at !== null, 404);

        $email = $invitation->email;
        $invitation->delete();

        return to_route('team')->with('team_status', 'Invitation for '.$email.' revoked.');
    }

    public function acceptInviteForm(string $token): View
    {
        return view('invites.accept', [
            'invitation' => $this->pendingInvitation($token),
            'token' => $token,
        ]);
    }

    public function processAcceptInvite(Request $request, string $token): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($token, $validated): User {
            $invitation = TeamInvitation::query()
                ->where('token', $token)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $invitation) {
                abort(404);
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => $validated['password'],
                'role' => $invitation->role,
            ]);

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function pendingInvitation(string $token): TeamInvitation
    {
        return TeamInvitation::query()
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();
    }
}
