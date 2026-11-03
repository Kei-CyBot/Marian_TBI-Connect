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
@if ($errors->any())
    <div role="alert" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

@php
    $statusBadge = match ($event->status) {
        'completed' => 'bg-emerald-100 text-emerald-700',
        'cancelled' => 'bg-rose-100 text-rose-700',
        default => 'bg-sky-100 text-sky-700',
    };
@endphp

<div class="mx-auto max-w-3xl space-y-5" x-data="{ inviteOpen: false, completeOpen: false, cancelOpen: false }">
    <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4 px-6 py-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Status</p>
                <span class="mt-1 inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusBadge }}">{{ ucfirst($event->status) }}</span>
                @if ($event->status === 'cancelled')
                    <p class="mt-3 text-sm text-slate-700">{{ $event->cancellation_reason }}</p>
                    <p class="mt-1 text-xs text-gray-500">Cancelled {{ $event->cancelled_at?->format('M d, Y g:i A') }}</p>
                @elseif ($event->status === 'completed')
                    <p class="mt-3 text-sm text-slate-700">Completed {{ $event->completed_at?->format('M d, Y g:i A') }}</p>
                    @if ($event->actual_attendees_count !== null)
                        <p class="mt-1 text-xs text-gray-500">{{ number_format($event->actual_attendees_count) }} actual attendee(s)</p>
                    @endif
                    @if ($event->post_event_notes)
                        <p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $event->post_event_notes }}</p>
                    @endif
                    @if ($event->post_event_document_path)
                        <a href="{{ route('events.post-event-document', $event) }}" class="mt-3 inline-flex text-sm font-semibold text-[#1a8a8e] hover:underline">
                            {{ $event->post_event_document_original_name ?: 'Post-event document' }}
                        </a>
                    @endif
                @endif
                @if ($event->archived_at)
                    <p class="mt-1 text-xs text-gray-500">Archived {{ $event->archived_at->format('M d, Y g:i A') }}</p>
                @endif
            </div>
            @if (auth()->user()->canManageRecords() && $event->status === 'scheduled')
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="completeOpen = true" class="rounded-xl bg-[#1a8a8e] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#146e72]">Mark Completed</button>
                    <button type="button" @click="cancelOpen = true" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100">Cancel Event</button>
                    @if ($event->archived_at)
                        <form method="POST" action="{{ route('events.restore', $event) }}">
                            @csrf
                            <button type="submit" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-gray-50">Restore</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('events.archive', $event) }}" onsubmit="return confirm('Archive this event and move it to the Archives page?')">
                            @csrf
                            <button type="submit" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-gray-50">Archive</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </section>
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

    <div x-show="completeOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @keydown.escape.window="completeOpen = false">
        <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl" @click.outside="completeOpen = false">
            <form method="POST" action="{{ route('events.complete', $event) }}" enctype="multipart/form-data">
                @csrf
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-slate-800">Mark event as completed</h3>
                    <p class="text-xs text-gray-500">Record attendance and optional post-event notes.</p>
                </div>
                <div class="space-y-4 px-6 py-4">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Actual attendees</span>
                        <input type="number" name="actual_attendees_count" min="0" max="100000" value="{{ old('actual_attendees_count') }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-[#1a8a8e] focus:ring-[#1a8a8e]">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Post-event notes</span>
                        <textarea name="post_event_notes" rows="4" maxlength="5000" class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-[#1a8a8e] focus:ring-[#1a8a8e]">{{ old('post_event_notes') }}</textarea>
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Post-event document</span>
                        <input type="file" name="post_event_document" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#eaf6f6] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[#146e72]">
                        <span class="mt-1 block text-xs text-gray-500">Optional. PDF, Word, Excel, or image up to 10 MB.</span>
                    </label>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-6 py-4">
                    <button type="button" @click="completeOpen = false" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-gray-100">Cancel</button>
                    <button type="submit" class="rounded-xl bg-[#1a8a8e] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#146e72]">Mark completed</button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @keydown.escape.window="cancelOpen = false">
        <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl" @click.outside="cancelOpen = false">
            <form method="POST" action="{{ route('events.cancel', $event) }}">
                @csrf
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-slate-800">Cancel event</h3>
                    <p class="text-xs text-gray-500">RSVP links will stop accepting responses.</p>
                </div>
                <div class="space-y-4 px-6 py-4">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Cancellation reason</span>
                        <textarea name="cancellation_reason" rows="4" maxlength="1000" required class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-[#b92369] focus:ring-[#b92369]">{{ old('cancellation_reason') }}</textarea>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border border-gray-200 px-4 py-3">
                        <input type="checkbox" name="send_cancellation_email" value="1" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-[#b92369]">
                        <span>
                            <span class="block text-sm font-semibold text-slate-800">Send cancellation email to invitees</span>
                            <span class="block text-xs text-gray-500">Notifies everyone who received an invitation.</span>
                        </span>
                    </label>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-6 py-4">
                    <button type="button" @click="cancelOpen = false" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-gray-100">Keep event</button>
                    <button type="submit" class="rounded-xl bg-[#b92369] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#a01c5b]">Cancel event</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
