@extends('layouts.app')
@section('title','Dashboard')
@section('eyebrow','Hybrid Hub · Overview')
@section('heading','Ecosystem Dashboard')
@section('subheading','Live snapshot across Internal Academic and External Industry tracks.')

@push('head')
<style>
    @keyframes activityIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
    .activity-in { animation: activityIn .35s ease-out both; }
</style>
@endpush

@section('content')
@php
    $recentActivity = $metrics['recentActivity']->map(fn ($log): array => [
        'id' => $log->id,
        'action' => $log->action,
        'description' => $log->description,
        'user' => $log->user?->name ?? 'System',
        'created_at_human' => $log->created_at?->diffForHumans(),
    ])->values();
    $rsvps = $metrics['rsvps'];
    $stats = [
        ['label'=>'Active Startups','all'=>$metrics['activeStartups']['all'],'internal'=>$metrics['activeStartups']['internal'],'external'=>$metrics['activeStartups']['external'],'delta'=>'Non-archived records'],
        ['label'=>'Network Contacts','all'=>$metrics['networkContacts']['all'],'internal'=>$metrics['networkContacts']['internal'],'external'=>$metrics['networkContacts']['external'],'delta'=>'Total contacts'],
        ['label'=>'Upcoming Events','all'=>$metrics['upcomingEvents']['all'],'internal'=>$metrics['upcomingEvents']['internal'],'external'=>$metrics['upcomingEvents']['external'],'delta'=>"{$rsvps['attending']} attending · {$rsvps['pending']} pending · {$rsvps['declined']} declined"],
        ['label'=>'Pending Intake','all'=>$metrics['pendingIntakeApplications']['all'],'internal'=>$metrics['pendingIntakeApplications']['internal'],'external'=>$metrics['pendingIntakeApplications']['external'],'delta'=>'Awaiting review'],
    ];
@endphp

<div x-data="{ stats:@js($stats), pipeline:@js($pipeline), get pipelineMax(){ return Math.max(1, ...this.pipeline.map(p=>p.all)) } }" class="space-y-6">

    {{-- Metric cards --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <template x-for="(s,i) in stats" :key="s.label">
            <div class="relative overflow-hidden rounded-2xl border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                 :class="i===0 ? 'grad border-transparent text-white' : 'border-gray-100 bg-white'">
                <p class="text-xs font-semibold uppercase tracking-wider" :class="i===0 ? 'text-white/70' : 'text-gray-400'" x-text="s.label"></p>
                <p class="mt-2 text-3xl font-bold tracking-tight" :class="i===0 ? '' : 'text-[#b92369]'" x-text="s[scope]"></p>
                <div class="mt-3 flex flex-wrap items-center gap-3 text-xs" :class="i===0 ? 'text-white/80' : 'text-gray-500'">
                    <span class="inline-flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full" :class="i===0?'bg-white':'bg-[#b92369]'"></span><span x-text="s.internal"></span> Int.</span>
                    <span class="inline-flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full" :class="i===0?'bg-white/60':'bg-[#1a8a8e]'"></span><span x-text="s.external"></span> Ext.</span>
                    <span class="ml-auto font-medium" x-text="s.delta"></span>
                </div>
            </div>
        </template>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Pipeline --}}
        <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-2">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Status Pipeline</h2>
                <div class="flex gap-3 text-xs text-gray-500">
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-[#b92369]"></span>Internal</span>
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-[#1a8a8e]"></span>External</span>
                </div>
            </div>
            <div class="mt-5 space-y-4">
                <template x-for="p in pipeline" :key="p.status">
                    <div>
                        <div class="mb-1 flex justify-between text-sm"><span class="font-medium" x-text="p.status"></span><span class="text-gray-500" x-text="scope==='all' ? p.all : p[scope]"></span></div>
                        <div class="flex h-3 overflow-hidden rounded-full bg-gray-100">
                            <div class="bg-[#b92369] transition-all duration-500" x-show="scope!=='external'" :style="`width:${p.internal/pipelineMax*100}%`"></div>
                            <div class="bg-[#1a8a8e] transition-all duration-500" x-show="scope!=='internal'" :style="`width:${p.external/pipelineMax*100}%`"></div>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        {{-- Real-time activity stream --}}
        <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm"
                 x-data="{
                     items: @js($recentActivity),
                     lastId: {{ $recentActivity->max('id') ?? 0 }},
                     endpoint: @js(route('activity-logs.stream')),
                     live: false,
                     start() { window.setInterval(() => this.poll(), 5000); },
                     async poll() {
                         try {
                             const response = await fetch(`${this.endpoint}?after_id=${this.lastId}`, { headers: { Accept: 'application/json' } });
                             if (!response.ok) return;
                             const rows = await response.json();
                             if (!rows.length) return;
                             this.items = [...rows.reverse(), ...this.items].slice(0, 8);
                             this.lastId = Math.max(this.lastId, ...rows.map((row) => row.id));
                             this.live = true;
                             window.setTimeout(() => { this.live = false; }, 1800);
                         } catch (error) {}
                     }
                 }"
                 x-init="start()">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Live Activity</h2>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#fcf0f5] px-2.5 py-1 text-[11px] font-semibold text-[#b92369]">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-[#b92369]"></span>
                    <span x-text="live ? 'New activity' : 'Live'"></span>
                </span>
            </div>
            <ul class="mt-4 space-y-4">
                <template x-for="log in items" :key="log.id">
                    <li class="activity-in flex gap-3">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-[#1a8a8e]"></span>
                        <div class="min-w-0">
                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-600" x-text="log.action"></span>
                            <p class="mt-1 text-sm" x-text="log.description"></p>
                            <p class="text-xs text-gray-400"><span x-text="log.user"></span> · <span x-text="log.created_at_human"></span></p>
                        </div>
                    </li>
                </template>
                <li x-show="items.length===0" class="text-sm text-gray-500">No activity logged yet.</li>
            </ul>
        </section>
    </div>

    {{-- Quick actions --}}
    @if(auth()->user()?->canManageRecords())
        <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="font-semibold">Quick Actions</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <a href="{{ route('events.create') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#b92369]/40 hover:bg-[#fcf0f5]">Create Event</a>
                <a href="{{ route('events.index') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#1a8a8e]/40 hover:bg-[#eef9f9]">Send Outreach</a>
                <a href="{{ route('incubatees', ['openOnboarding' => '1']) }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#b92369]/40 hover:bg-[#fcf0f5]">Add Startup</a>
                <form method="POST" action="{{ route('google-drive.sync') }}">
                    @csrf
                    <button type="submit" class="w-full rounded-xl border border-gray-200 px-4 py-3 text-left text-sm font-semibold text-slate-700 transition hover:border-[#1a8a8e]/40 hover:bg-[#eef9f9]">Sync Drive Storage</button>
                </form>
            </div>
        </section>
    @endif
</div>
@endsection
