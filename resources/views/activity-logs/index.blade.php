@extends('layouts.app')
@section('title','Activity Log')
@section('eyebrow','Administration')
@section('heading','Activity Log')
@section('subheading','Search and filter system activity, with new entries arriving in real time.')

@push('head')
<style>
    @keyframes activityIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
    .activity-in { animation: activityIn .35s ease-out both; }
</style>
@endpush

@section('content')
@php
    $initialLogs = $logs->getCollection()->map(fn ($log): array => [
        'id' => $log->id,
        'action' => $log->action,
        'description' => $log->description,
        'user' => $log->user?->name ?? 'System',
        'ip_address' => $log->ip_address,
        'created_at_human' => $log->created_at?->diffForHumans(),
    ])->values();
@endphp

<div class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
    <form method="GET" action="{{ route('activity-logs.index') }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-4">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search description or action"
                   class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-[#b92369] focus:outline-none">
            <select name="user_id" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-[#b92369] focus:outline-none">
                <option value="">All users</option>
                @foreach ($users as $filterUser)
                    <option value="{{ $filterUser->id }}" @selected((string) request('user_id') === (string) $filterUser->id)>{{ $filterUser->name }}</option>
                @endforeach
            </select>
            <select name="action" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-[#b92369] focus:outline-none">
                <option value="">All actions</option>
                @foreach ($actions as $filterAction)
                    <option value="{{ $filterAction }}" @selected(request('action') === $filterAction)>{{ ucfirst(str_replace('_', ' ', $filterAction)) }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-xl bg-[#b92369] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#9a1b55]">Filter</button>
                <a href="{{ route('activity-logs.index') }}" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-gray-50">Reset</a>
            </div>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
         x-data="{
             items: @js($initialLogs),
             lastId: {{ $lastId }},
             endpoint: @js(route('activity-logs.stream')),
             live: false,
             start() { window.setInterval(() => this.poll(), 5000); },
             async poll() {
                 try {
                     const response = await fetch(`${this.endpoint}?after_id=${this.lastId}`, { headers: { Accept: 'application/json' } });
                     if (!response.ok) return;
                     const rows = await response.json();
                     if (!rows.length) return;
                     this.items = [...rows.reverse(), ...this.items];
                     this.lastId = Math.max(this.lastId, ...rows.map((row) => row.id));
                     this.live = true;
                     window.setTimeout(() => { this.live = false; }, 1800);
                 } catch (error) {}
             }
         }"
         x-init="start()">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
            <div>
                <h2 class="font-semibold text-slate-900">Live activity stream</h2>
                <p class="text-xs text-gray-500">Refreshes automatically every 5 seconds.</p>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-[#fcf0f5] px-3 py-1 text-xs font-semibold text-[#b92369]">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-[#b92369]"></span>
                <span x-text="live ? 'New activity' : 'Live'"></span>
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                    <tr>
                        <th class="px-5 py-3">Description</th>
                        <th class="px-3 py-3">Action</th>
                        <th class="px-3 py-3">User</th>
                        <th class="px-3 py-3">IP Address</th>
                        <th class="px-5 py-3">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="log in items" :key="log.id">
                        <tr class="activity-in hover:bg-gray-50">
                            <td class="px-5 py-3 text-slate-900" x-text="log.description"></td>
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-gray-600" x-text="log.action"></span>
                            </td>
                            <td class="px-3 py-3 text-gray-700" x-text="log.user"></td>
                            <td class="px-3 py-3 text-gray-700" x-text="log.ip_address || '—'"></td>
                            <td class="px-5 py-3 text-gray-700" x-text="log.created_at_human"></td>
                        </tr>
                    </template>
                    <tr x-show="items.length===0">
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-700">No activity logged yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="border-t border-gray-200 px-5 py-3">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
