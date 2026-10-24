@extends('layouts.app')
@section('title','Team & RBAC')
@section('eyebrow','Access Control')
@section('heading','Team & Role-Based Access')
@section('subheading','Super Admin manages roles; Admin manages workspace records; Members/Incubatees have view-only access.')

@section('content')
@if (session('team_status'))
    <div role="status" class="mb-4 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('team_status') }}</div>
@endif
@if (auth()->user()->canManageRecords() || auth()->user()->isSuperAdmin())
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
        <div>
            <h2 class="text-sm font-bold text-slate-900">Invite Team Member</h2>
            <p class="mt-1 text-xs text-gray-600">Send an invitation email with a secure token that expires in 7 days.</p>
        </div>
        <button type="button" x-data @click="$dispatch('open-invite-modal')" class="rounded-lg bg-[#1a8a8e] px-3 py-2 text-sm font-semibold text-white hover:bg-[#146e72]">Invite</button>
    </div>
@endif
<div class="mb-5 grid gap-3 md:grid-cols-3">
    <section class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"><h2 class="text-sm font-bold text-slate-900">Super Admin</h2><p class="mt-1 text-xs leading-5 text-gray-600">Full workspace access and sole authority to assign roles.</p></section>
    <section class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"><h2 class="text-sm font-bold text-slate-900">Admin</h2><p class="mt-1 text-xs leading-5 text-gray-600">Can create, edit, and manage workspace records.</p></section>
    <section class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"><h2 class="text-sm font-bold text-slate-900">Member/Incubatee</h2><p class="mt-1 text-xs leading-5 text-gray-600">Can view workspace pages but cannot change shared records.</p></section>
</div>
<div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
    <table class="min-w-full text-sm">
        <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
            <tr><th class="px-5 py-3">Member</th><th class="px-3 py-3">Email</th><th class="px-3 py-3">Role</th><th class="px-3 py-3">Action</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse ($team as $member)
                <tr>
                    <td class="px-5 py-3 font-medium text-slate-900">{{ $member->name }} @if($member->is(auth()->user()))<span class="ml-1 text-xs font-normal text-gray-500">(you)</span>@endif</td>
                    <td class="px-3 py-3 text-gray-600">{{ $member->email }}</td>
                    <td class="px-3 py-3 text-gray-700">{{ $member->roleLabel() }}</td>
                    <td class="px-3 py-3">
                        @if (auth()->user()->isSuperAdmin())
                            <form method="POST" action="{{ route('team.roles.update', $member) }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                @method('PUT')
                                <select name="role" aria-label="Role for {{ $member->name }}" class="rounded-lg border border-slate-line bg-white px-2 py-1.5 text-xs text-slate-800 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                                    <option value="super_admin" @selected($member->role === \App\Models\User::ROLE_SUPER_ADMIN)>Super Admin</option>
                                    <option value="admin" @selected($member->role === \App\Models\User::ROLE_ADMIN)>Admin</option>
                                    <option value="member_incubatee" @selected($member->role === \App\Models\User::ROLE_MEMBER_INCUBATEE)>Member/Incubatee</option>
                                </select>
                                <button type="submit" class="rounded-lg bg-[#1a8a8e] px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-[#146e72]">Save role</button>
                            </form>
                        @else
                            <span class="text-xs text-gray-500">Super Admin only</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500">No team accounts are registered.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if (isset($invitations) && $invitations->isNotEmpty())
    <div class="mt-6 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-3">
            <h2 class="text-sm font-bold text-slate-900">Pending Invitations</h2>
        </div>
        <table class="min-w-full text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr><th class="px-5 py-3">Email</th><th class="px-3 py-3">Role</th><th class="px-3 py-3">Expires</th><th class="px-3 py-3">Action</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach ($invitations as $invitation)
                    <tr>
                        <td class="px-5 py-3 text-slate-900">{{ $invitation->email }}</td>
                        <td class="px-3 py-3 text-gray-700">{{ \App\Models\User::ROLE_SUPER_ADMIN === $invitation->role ? 'Super Admin' : ($invitation->role === \App\Models\User::ROLE_MEMBER_INCUBATEE ? 'Member/Incubatee' : 'Admin') }}</td>
                        <td class="px-3 py-3 text-gray-600">{{ $invitation->expires_at->format('M d, Y') }}</td>
                        <td class="px-3 py-3">
                            @if (auth()->user()->canManageRecords() || auth()->user()->isSuperAdmin())
                                <form method="POST" action="{{ route('team.invitations.revoke', $invitation) }}" onsubmit="return confirm('Revoke this invitation?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-gray-50">Revoke</button>
                                </form>
                            @else
                                <span class="text-xs text-gray-500">-</span>
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
        <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <h2 class="text-base font-bold text-slate-900">Invite Team Member</h2>
                <button type="button" @click="open = false" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-50">✕</button>
            </div>
            <form method="POST" action="{{ route('team.invite') }}" class="space-y-4 px-5 py-4">
                @csrf
                <div>
                    <label for="invite-email" class="mb-1.5 block text-sm font-semibold text-slate-700">Email</label>
                    <input id="invite-email" name="email" type="email" required class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm">
                </div>
                <div>
                    <label for="invite-role" class="mb-1.5 block text-sm font-semibold text-slate-700">Role</label>
                    <select id="invite-role" name="role" required class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm">
                        <option value="admin">Admin</option>
                        <option value="member_incubatee">Member/Incubatee</option>
                        @if (auth()->user()->isSuperAdmin())
                            <option value="super_admin">Super Admin</option>
                        @endif
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                    <button type="submit" class="rounded-lg bg-[#1a8a8e] px-3 py-2 text-sm font-semibold text-white">Send Invite</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
