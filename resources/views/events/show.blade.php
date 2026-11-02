@extends('layouts.app')
@section('title', $event->title)
@section('eyebrow','Events & Outreach')
@section('heading', $event->title)
@section('subheading', $event->kind)
@section('banner-actions')
    <a href="{{ route('events') }}" class="rounded-xl bg-white/15 px-4 py-2 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/25">Back to events</a>
    @if (auth()->user()->canManageRecords())
        <a href="{{ route('events.edit', $event) }}" class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-[#b92369] shadow-sm transition hover:bg-[#fcf0f5]">Edit event</a>
        <button type="button" @click="$dispatch('open-broadcast', { id: {{ $event->id }}, title: @js($event->title) })" class="rounded-xl bg-white/15 px-4 py-2 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/25">Email contacts</button>
    @endif
@endsection

@section('content')
@if (session('event_status'))
    <div role="status" class="mb-4 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('event_status') }}</div>
@endif
@if (session('email_error'))
    <div role="alert" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ session('email_error') }}</div>
@endif

<div class="mx-auto max-w-3xl space-y-5" x-data="{ inviteOpen: false }">
    <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="h-1.5 {{ $event->scope === 'internal' ? 'bg-[#b92369]' : ($event->scope === 'external' ? 'bg-[#1a8a8e]' : 'grad') }}"></div>
        <dl class="divide-y divide-gray-100">
            <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Scope</dt>
                <dd class="text-sm font-medium text-slate-800 sm:col-span-2">{{ $event->scope === 'internal' ? 'Internal Academic' : ($event->scope === 'external' ? 'External Industry' : 'Hybrid · Public') }}</dd>
            </div>
            <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Starts</dt>
                <dd class="text-sm text-slate-800 sm:col-span-2">{{ $event->starts_at->format('g:i A - M d, Y') }}</dd>
            </div>
            <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Ends</dt>
                <dd class="text-sm text-slate-800 sm:col-span-2">{{ $event->ends_at->format('g:i A - M d, Y') }}</dd>
            </div>
            <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Host</dt>
                <dd class="text-sm text-slate-800 sm:col-span-2">{{ $event->host }}</dd>
            </div>
            <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Location</dt>
                <dd class="break-words text-sm text-slate-800 sm:col-span-2">
                    @if ($event->virtual)
                        <a href="{{ $event->location }}" class="font-medium text-[#1a8a8e] hover:underline">{{ $event->location }}</a>
                    @else
                        {{ $event->location }}
                    @endif
                </dd>
            </div>
        </dl>
    </section>

    <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-800">RSVP attendees</h2>
                <p class="text-xs text-gray-500">{{ $invitations->count() }} invitation(s)</p>
            </div>
            @if (auth()->user()->canManageRecords())
                <button type="button" @click="inviteOpen = true" class="rounded-xl bg-[#b92369] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#a01c5b]">Send Invites</button>
            @endif
        </div>

        @if ($invitations->isEmpty())
            <p class="px-6 py-6 text-sm text-gray-500">No invitations sent yet.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($invitations as $invitation)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-800">{{ $invitation->name ?: $invitation->email }}</p>
                            <p class="truncate text-xs text-gray-500">{{ $invitation->email }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ match ($invitation->status) {
                            'attending' => 'bg-emerald-100 text-emerald-700',
                            'declined' => 'bg-rose-100 text-rose-700',
                            default => 'bg-amber-100 text-amber-700',
                        } }}">{{ ucfirst($invitation->status) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div x-show="inviteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @keydown.escape.window="inviteOpen = false">
        <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl" @click.outside="inviteOpen = false">
            <form method="POST" action="{{ route('events.invitations.send', $event) }}">
                @csrf
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-slate-800">Send invitations</h3>
                    <p class="text-xs text-gray-500">Select contacts to invite. Each receives a personal Attend / Decline link.</p>
                </div>
                <div class="max-h-64 overflow-y-auto px-6 py-4">
                    @forelse ($contacts as $contact)
                        <label class="flex items-center gap-3 py-2">
                            <input type="checkbox" name="contact_ids[]" value="{{ $contact->id }}" class="h-4 w-4 rounded border-gray-300 text-[#b92369]">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-slate-800">{{ $contact->name ?: $contact->email }}</span>
                                <span class="block truncate text-xs text-gray-500">{{ $contact->email }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-gray-500">No contacts with email addresses yet.</p>
                    @endforelse
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-6 py-4">
                    <button type="button" @click="inviteOpen = false" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-gray-100">Cancel</button>
                    <button type="submit" class="rounded-xl bg-[#b92369] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#a01c5b]">Send invitations</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
