@extends('layouts.app')
@section('title','Team & RBAC')
@section('eyebrow','Access Control')
@section('heading','Team & Role-Based Access')
@section('subheading','Super Admin manages roles; Admin manages workspace records; Members/Incubatees have view-only access.')

@section('content')
@if (session('team_status'))
    <div role="status" class="mb-4 rounded-lg border border-teal-200 bg-teal-50/80 px-4 py-3 text-sm font-medium text-teal-800">{{ session('team_status') }}</div>
@endif
@if (auth()->user()->canManageRecords() || auth()->user()->isSuperAdmin())
    <div class="ui-card mb-5 flex flex-wrap items-center justify-between gap-3 p-4">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">Invite Team Member</h2>
            <p class="mt-1 text-xs text-slate-500">Send an invitation email with a secure token that expires in 7 days.</p>
        </div>
        <button type="button" x-data @click="$dispatch('open-invite-modal')" class="rounded-lg bg-[#9E2A5B] px-3.5 py-2 text-sm font-medium text-white shadow-2xs transition hover:bg-[#86224C]">Invite</button>
    </div>
@endif
<div class="mb-5 grid gap-3 md:grid-cols-3">
    <section class="ui-card p-4"><h2 class="text-sm font-semibold text-slate-900">Super Admin</h2><p class="mt-1 text-xs leading-5 text-slate-500">Full workspace access and sole authority to assign roles.</p></section>
    <section class="ui-card p-4"><h2 class="text-sm font-semibold text-slate-900">Admin</h2><p class="mt-1 text-xs leading-5 text-slate-500">Can create, edit, and manage workspace records.</p></section>
    <section class="ui-card p-4"><h2 class="text-sm font-semibold text-slate-900">Member/Incubatee</h2><p class="mt-1 text-xs leading-5 text-slate-500">Can view workspace pages but cannot change shared records.</p></section>
</div>
<div class="mb-4 flex items-center gap-2">
    <a href="{{ route('team') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $tab === 'active' ? 'bg-[#9E2A5B] text-white shadow-2xs' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">Active ({{ $team->count() }})</a>
    <a href="{{ route('team', ['tab' => 'archived']) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $tab === 'archived' ? 'bg-[#9E2A5B] text-white shadow-2xs' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">Archived ({{ $archivedTeam->count() }})</a>
</div>
@if ($tab === 'active')
<div class="ui-card overflow-hidden">
    <table class="ui-table">
        <thead>
            <tr><th>Member</th><th>Email</th><th>Role</th><th>Contact Status</th><th>Action</th></tr>
        </thead>
        <tbody>
            @forelse ($team as $member)
                @php
                    $match = $contactMatches[strtolower((string) $member->email)] ?? null;
                    $linkStatus = $member->contact_id ? 'linked' : ($match ? 'email_match' : 'unlinked');
                @endphp
                <tr>
                    <td class="px-5 font-medium text-slate-900">
                        {{ $member->name }}
                        @if($member->is(auth()->user()))<span class="ml-1 text-xs font-normal text-slate-400">(you)</span>@endif
                    </td>
                    <td class="px-3 text-slate-600">{{ $member->email }}</td>
                    <td class="px-3 text-slate-700">{{ $member->roleLabel() }}</td>
                    <td class="px-3">
                        @if($linkStatus === 'linked')
                            <span class="ui-badge bg-teal-50/80 text-teal-800 ring-teal-200/70"><span class="ui-badge-dot"></span>Linked</span>
                            @if($member->contact)<span class="mt-1 block text-xs text-slate-500">{{ $member->contact->name }}</span>@endif
                        @elseif($linkStatus === 'email_match')
                            <span class="ui-badge bg-slate-100 text-slate-700 ring-slate-200/80"><span class="ui-badge-dot"></span>Email match</span>
                            <form method="POST" action="{{ route('team.contacts.link', $member) }}" class="mt-1">@csrf
                                <button type="submit" class="text-xs font-medium text-teal-700 hover:text-teal-900">Link contact</button>
                            </form>
                        @else
                            <span class="ui-badge bg-slate-100 text-slate-600 ring-slate-200/80"><span class="ui-badge-dot"></span>Unlinked</span>
                            <form method="POST" action="{{ route('team.contacts.link', $member) }}" class="mt-1">@csrf
                                <button type="submit" class="text-xs font-medium text-teal-700 hover:text-teal-900">Create contact</button>
                            </form>
                        @endif
                    </td>
                    <td class="px-3">
                        @if (auth()->user()->isSuperAdmin())
                            <form method="POST" action="{{ route('team.roles.update', $member) }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                @method('PUT')
                                <select name="role" aria-label="Role for {{ $member->name }}" class="ui-select w-auto text-xs">
                                    <option value="super_admin" @selected($member->role === \App\Models\User::ROLE_SUPER_ADMIN)>Super Admin</option>
                                    <option value="admin" @selected($member->role === \App\Models\User::ROLE_ADMIN)>Admin</option>
                                    <option value="member_incubatee" @selected($member->role === \App\Models\User::ROLE_MEMBER_INCUBATEE)>Member/Incubatee</option>
                                </select>
                                <button type="submit" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">Save role</button>
                            </form>
                        @else
                            <span class="text-xs text-slate-400">Super Admin only</span>
                        @endif
                        @if (! $member->is(auth()->user()) && (auth()->user()->canManageRecords() || auth()->user()->isSuperAdmin()))
                            <form method="POST" action="{{ route('team.archive', $member) }}" class="mt-2" onsubmit="return confirm('Archive {{ $member->name }}? The linked contact will be archived too.')">
                                @csrf
                                <button type="submit" class="rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800 transition hover:bg-amber-100">Archive</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">No team accounts are registered.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@else
<div class="ui-card overflow-hidden">
    <table class="ui-table">
        <thead>
            <tr><th>Member</th><th>Email</th><th>Role</th><th>Archived</th><th>Action</th></tr>
        </thead>
        <tbody>
            @forelse ($archivedTeam as $member)
                <tr>
                    <td class="px-5 font-medium text-slate-900">{{ $member->name }}</td>
                    <td class="px-3 text-slate-600">{{ $member->email }}</td>
                    <td class="px-3 text-slate-700">{{ $member->roleLabel() }}</td>
                    <td class="px-3 tabular-nums text-slate-600">{{ $member->deleted_at?->format('M j, Y') }}</td>
                    <td class="px-3">
                        <form method="POST" action="{{ route('team.restore', $member->id) }}">@csrf
                            <button type="submit" class="rounded-lg border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-800 transition hover:bg-teal-100">Restore</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">No archived team accounts.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endif

@if (isset($invitations) && $invitations->isNotEmpty())
    <div class="ui-card mt-6 overflow-hidden">
        <div class="border-b border-slate-200/80 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Pending Invitations</h2>
        </div>
        <table class="ui-table">
            <thead>
                <tr><th>Email</th><th>Role</th><th>Expires</th><th>Action</th></tr>
            </thead>
            <tbody>
                @foreach ($invitations as $invitation)
                    <tr>
                        <td class="px-5 text-slate-900">{{ $invitation->email }}</td>
                        <td class="px-3 text-slate-700">{{ \App\Models\User::ROLE_SUPER_ADMIN === $invitation->role ? 'Super Admin' : ($invitation->role === \App\Models\User::ROLE_MEMBER_INCUBATEE ? 'Member/Incubatee' : 'Admin') }}</td>
                        <td class="px-3 tabular-nums text-slate-600">{{ $invitation->expires_at->format('M d, Y') }}</td>
                        <td class="px-3">
                            @if (auth()->user()->canManageRecords() || auth()->user()->isSuperAdmin())
                                <button type="button" @click="$dispatch('confirm-revoke-invite', {id: {{ $invitation->id }}, action: '{{ route('team.invitations.revoke', $invitation) }}'})" class="rounded-lg border border-rose-200 px-2.5 py-1 text-xs font-medium text-rose-700 transition hover:bg-rose-50">Revoke</button>
                            @else
                                <span class="text-xs text-slate-400">-</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if (auth()->user()->canManageRecords() || auth()->user()->isSuperAdmin())
    <div x-data="{ open: false }" x-on:open-invite-modal.window="open = true" x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200/80 px-5 py-3">
                <h2 class="text-base font-semibold text-slate-900">Invite Team Member</h2>
                <button type="button" @click="open = false" class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-50" aria-label="Close">✕</button>
            </div>
            <form method="POST" action="{{ route('team.invite') }}" class="space-y-4 px-5 py-4">
                @csrf
                <div>
                    <label for="invite-email" class="ui-label">Contact</label>
                    <select id="invite-email" name="email" required class="ui-select">
                        <option value="" disabled @selected(! old('email'))>Select a contact eligible for onboarding</option>
                        @foreach ($candidates as $candidate)
                            <option value="{{ $candidate->email }}" @selected(old('email') === $candidate->email)>{{ $candidate->name }} — {{ $candidate->email }}</option>
                        @endforeach
                    </select>
                    @error('email')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                    @if ($candidates->isEmpty())
                        <p class="mt-1 text-xs text-slate-500">No eligible contacts are available. Add a contact with a valid email first.</p>
                    @endif
                </div>
                <div>
                    <label for="invite-role" class="ui-label">Role</label>
                    <select id="invite-role" name="role" required class="ui-select">
                        <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                        <option value="member_incubatee" @selected(old('role') === 'member_incubatee')>Member/Incubatee</option>
                        @if (auth()->user()->isSuperAdmin())
                            <option value="super_admin" @selected(old('role') === 'super_admin')>Super Admin</option>
                        @endif
                    </select>
                    @error('role')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="open = false" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Cancel</button>
                    <button type="submit" @disabled($candidates->isEmpty()) class="rounded-lg bg-[#9E2A5B] px-3.5 py-2 text-sm font-medium text-white shadow-2xs transition hover:bg-[#86224C] disabled:opacity-50">Send Invite</button>
                </div>
            </form>
        </div>
    </div>
@endif

    <div x-data="{confirmOpen:false, revoke:{id:null, action:''}}" @confirm-revoke-invite.window="revoke = $event.detail; confirmOpen = true" x-show="confirmOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
            <div class="px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Revoke Invitation</h3>
                <p class="mt-2 text-sm text-slate-500">Revoke this invitation?</p>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200/80 px-6 py-4">
                <button type="button" @click="confirmOpen=false" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Cancel</button>
                <button type="button" @click="
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = revoke.action;
                    const csrf = document.createElement('input');
                    csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '{{ csrf_token() }}';
                    const method = document.createElement('input');
                    method.type = 'hidden'; method.name = '_method'; method.value = 'DELETE';
                    form.appendChild(csrf);
                    form.appendChild(method);
                    document.body.appendChild(form);
                    form.submit();
                    confirmOpen=false;
                " class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-800">Yes, Revoke Invite</button>
            </div>
        </div>
    </div>
@endsection
