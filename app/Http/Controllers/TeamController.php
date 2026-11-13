<?php

namespace App\Http\Controllers;

use App\Mail\TeamInviteMail;
use App\Models\Contact;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $team = User::query()->with('contact')->orderBy('name')->get(['id', 'name', 'email', 'role', 'contact_id']);
        $archivedTeam = User::onlyTrashed()->with('contact')->orderByDesc('deleted_at')->get(['id', 'name', 'email', 'role', 'contact_id', 'deleted_at']);
        $tab = $request->query('tab') === 'archived' ? 'archived' : 'active';
        $invitations = TeamInvitation::query()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderBy('email')
            ->get();

        $contactMatches = Contact::query()
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->get(['id', 'name', 'email'])
            ->keyBy(fn (Contact $contact): string => strtolower((string) $contact->email));

        $unavailable = $team->pluck('email')
            ->merge($invitations->pluck('email'))
            ->map(fn (?string $email): string => strtolower((string) $email))
            ->all();

        $candidates = Contact::query()
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->orderBy('name')
            ->get(['name', 'email'])
            ->reject(fn (Contact $contact): bool => in_array(strtolower($contact->email), $unavailable, true))
            ->unique(fn (Contact $contact): string => strtolower($contact->email))
            ->values();

        return view('team', [
            'team' => $team,
            'archivedTeam' => $archivedTeam,
            'tab' => $tab,
            'invitations' => $invitations,
            'candidates' => $candidates,
            'contactMatches' => $contactMatches,
        ]);
    }

    public function archive(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot archive your own account.');

        DB::transaction(function () use ($user): void {
            $target = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($target->isSuperAdmin()) {
                abort_if(
                    User::query()->where('role', User::ROLE_SUPER_ADMIN)->lockForUpdate()->count() < 2,
                    422,
                    'At least one Super Admin account must remain active.'
                );
            }

            $target->contact()->withTrashed()->first()?->delete();
            $target->delete();
        });

        return to_route('team')->with('team_status', $user->name.' was archived.');
    }

    public function restore(string $user): RedirectResponse
    {
        $record = User::onlyTrashed()->findOrFail($user);
        $record->restore();
        $record->contact()->withTrashed()->first()?->restore();

        return to_route('team', ['tab' => 'archived'])->with('team_status', $record->name.' was restored.');
    }

    public function linkContact(User $user): RedirectResponse
    {
        $contact = Contact::query()
            ->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])
            ->first();

        if (! $contact) {
            $contact = Contact::create([
                'side' => 'external',
                'name' => $user->name,
                'email' => $user->email,
                'affiliation_type' => 'External',
                'role' => 'Member',
                'domains' => [],
                'technical_skills' => [],
                'incubatees' => [],
                'activity_log' => [],
            ]);
        }

        abort_if(
            $user->contact_id !== null && $user->contact_id !== $contact->id,
            422,
            'This account is already linked to a different contact.'
        );

        $user->forceFill(['contact_id' => $contact->id])->save();

        return to_route('team')->with('team_status', $user->name.' is linked to contact '.$contact->name.'.');
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
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(User::ROLES)],
        ]);

        if (! Contact::query()->whereRaw('LOWER(email) = ?', [$validated['email']])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Select a contact that is eligible for team onboarding.',
            ]);
        }

        if ($validated['role'] === User::ROLE_SUPER_ADMIN && ! $request->user()->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'role' => 'Only a Super Admin can assign the Super Admin role.',
            ]);
        }

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
                'contact_id' => Contact::query()
                    ->whereRaw('LOWER(email) = ?', [strtolower($invitation->email)])
                    ->value('id'),
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
