@extends('layouts.app')
@section('title','Dashboard')
@section('eyebrow','Hybrid Hub · Overview')
@section('heading','Ecosystem Dashboard')
@section('subheading')
    Live snapshot across <span class="font-medium text-rose-800">Internal Academic</span> and <span class="font-medium text-teal-700">External Industry</span> tracks.
@endsection
@section('banner-actions')
    <a href="{{ route('dashboard.export-csv') }}"
       class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-2xs transition hover:border-teal-300/80 hover:bg-teal-50/40 focus:outline-none focus:ring-2 focus:ring-teal-700/10">
        <svg class="w-4 h-4 text-teal-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 2 0 01-2-2V5a2 2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 2 0 01-2 2z"/>
        </svg>
        <span>Export Report (CSV)</span>
    </a>
@endsection

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
        ['label'=>'Active Startups','all'=>$metrics['activeStartups']['all'],'internal'=>$metrics['activeStartups']['internal'],'external'=>$metrics['activeStartups']['external'],'delta'=>'Active records','deltaTitle'=>'Non-archived records','tone'=>'rose','icon'=>'M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10V11M4 7v10l8 4'],
        ['label'=>'Network Contacts','all'=>$metrics['networkContacts']['all'],'internal'=>$metrics['networkContacts']['internal'],'external'=>$metrics['networkContacts']['external'],'delta'=>'Total contacts','deltaTitle'=>'Total contacts','tone'=>'teal','icon'=>'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M16 3.13a4 4 0 010 7.75M12 7a4 4 0 11-8 0 4 4 0 018 0z'],
        ['label'=>'Upcoming Events','all'=>$metrics['upcomingEvents']['all'],'internal'=>$metrics['upcomingEvents']['internal'],'external'=>$metrics['upcomingEvents']['external'],'delta'=>"{$rsvps['attending']} attending · {$rsvps['pending']} pending",'deltaTitle'=>"{$rsvps['attending']} attending · {$rsvps['pending']} pending · {$rsvps['declined']} declined",'tone'=>'teal','icon'=>'M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['label'=>'Pending Intake','all'=>$metrics['pendingIntakeApplications']['all'],'internal'=>$metrics['pendingIntakeApplications']['internal'],'external'=>$metrics['pendingIntakeApplications']['external'],'delta'=>'Awaiting review','deltaTitle'=>'Awaiting review','tone'=>'rose','icon'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
@endphp

<div x-data="{ stats:@js($stats), pipeline:@js($pipeline), get pipelineMax(){ return Math.max(1, ...this.pipeline.map(p=>p.all)) } }" class="space-y-6">

    {{-- Metric cards --}}
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <template x-for="(s,i) in stats" :key="s.label">
            <div class="ui-card relative overflow-hidden p-5 transition hover:-translate-y-0.5 hover:shadow-sm">
                <span class="absolute right-5 top-5 flex h-9 w-9 items-center justify-center rounded-lg ring-1 ring-inset"
                      :class="s.tone === 'rose' ? 'bg-rose-50/70 text-rose-800 ring-rose-200/60' : 'bg-teal-50/70 text-teal-800 ring-teal-200/60'">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="s.icon"/>
                    </svg>
                </span>
                <p class="pr-12 text-xs font-medium text-slate-400" x-text="s.label"></p>
                <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-slate-900" x-text="s[scope]"></p>
                <div class="mt-4 flex items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-3">
                        <span class="inline-flex items-center gap-1 whitespace-nowrap"><span class="h-1.5 w-1.5 rounded-full bg-rose-700/80"></span><span class="tabular-nums" x-text="s.internal"></span> Int.</span>
                        <span class="inline-flex items-center gap-1 whitespace-nowrap"><span class="h-1.5 w-1.5 rounded-full bg-teal-700/80"></span><span class="tabular-nums" x-text="s.external"></span> Ext.</span>
                    </span>
                    <span class="min-w-0 truncate text-right font-medium" :title="s.deltaTitle" x-text="s.delta"></span>
                </div>
            </div>
        </template>
    </div>

    <!-- Executive Summary Section Header -->
    <div class="mt-8 mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-base font-semibold text-slate-900">Executive Summary Analytics</h3>
            <p class="text-sm text-slate-500">Uploaded files, technical needs, and resource allocations at a glance.</p>
        </div>
    </div>

    <!-- Analytics Cards Grid (3 Columns) -->
    <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
        <section class="ui-card relative p-5">
            <span class="absolute right-5 top-5 flex h-9 w-9 items-center justify-center rounded-lg bg-teal-50/70 text-teal-800 ring-1 ring-inset ring-teal-200/60">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V7l-4-4H7a2 2 0 00-2 2v14a2 2 0 002 2zM9 13h6M9 17h4"/>
                </svg>
            </span>
            <p class="pr-12 text-xs font-medium text-slate-400">Total Uploaded Files</p>
            <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-slate-900">{{ number_format($metrics['uploadedFiles']['total']) }}</p>
            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                <span><strong class="tabular-nums text-slate-800">{{ number_format($metrics['uploadedFiles']['technicalNeeds']) }}</strong> technical needs</span>
                <span><strong class="tabular-nums text-slate-800">{{ number_format($metrics['uploadedFiles']['repository']) }}</strong> document repository</span>
            </div>
        </section>

        <section class="ui-card relative p-5">
            <span class="absolute right-5 top-5 flex h-9 w-9 items-center justify-center rounded-lg bg-rose-50/70 text-rose-800 ring-1 ring-inset ring-rose-200/60">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
            </span>
            <p class="pr-12 text-xs font-medium text-slate-400">Technical Needs Summary</p>
            <div class="mt-2 flex items-end gap-3">
                <p class="text-3xl font-semibold tabular-nums tracking-tight text-slate-900">{{ number_format($metrics['technicalNeeds']['total']) }}</p>
                <p class="pb-1 text-xs font-medium tabular-nums text-amber-600">{{ number_format($metrics['technicalNeeds']['pending']) }} pending review</p>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <span class="ui-badge bg-rose-50/80 text-rose-800 ring-rose-200/70"><span class="ui-badge-dot"></span>High {{ $metrics['technicalNeeds']['priorities']['High'] }}</span>
                <span class="ui-badge bg-amber-50/80 text-amber-800 ring-amber-200/70"><span class="ui-badge-dot"></span>Moderate {{ $metrics['technicalNeeds']['priorities']['Moderate'] }}</span>
                <span class="ui-badge bg-slate-100 text-slate-700 ring-slate-200/80"><span class="ui-badge-dot"></span>Low {{ $metrics['technicalNeeds']['priorities']['Low'] }}</span>
            </div>
        </section>

        <section class="ui-card relative p-5">
            <span class="absolute right-5 top-5 flex h-9 w-9 items-center justify-center rounded-lg bg-teal-50/70 text-teal-800 ring-1 ring-inset ring-teal-200/60">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10V11M4 7v10l8 4"/>
                </svg>
            </span>
            <p class="pr-12 text-xs font-medium text-slate-400">Resource &amp; Tool Allocations</p>
            <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-slate-900">{{ number_format($metrics['resourceAllocations']['total']) }}</p>
            <p class="mt-3 text-xs text-slate-500">Estimated cost <strong class="tabular-nums text-slate-800">₱{{ number_format($metrics['resourceAllocations']['estimatedCost'], 2) }}</strong></p>
        </section>
    </div>

        {{-- Pipeline --}}
        <section class="ui-card p-6 lg:col-span-2">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-slate-900">Status Pipeline</h2>
                <div class="flex gap-3 text-xs text-slate-500">
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-[#b92369]"></span>Internal</span>
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-[#1a8a8e]"></span>External</span>
                </div>
            </div>
            <div class="mt-5 space-y-4">
                <template x-for="p in pipeline" :key="p.status">
                    <div>
                        <div class="mb-1 flex justify-between text-sm"><span class="font-medium" x-text="p.status"></span><span class="tabular-nums text-slate-500" x-text="scope==='all' ? p.all : p[scope]"></span></div>
                        <div class="flex h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="bg-[#b92369] transition-all duration-500" x-show="scope!=='external'" :style="`width:${p.internal/pipelineMax*100}%`"></div>
                            <div class="bg-[#1a8a8e] transition-all duration-500" x-show="scope!=='internal'" :style="`width:${p.external/pipelineMax*100}%`"></div>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        {{-- Real-time activity stream --}}
        <section class="ui-card p-6"
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
                <h2 class="font-semibold text-slate-900">Live Activity</h2>
                <span class="ui-badge bg-[#fcf0f5] text-[#b92369] ring-[#b92369]/15">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-[#b92369]"></span>
                    <span x-text="live ? 'New activity' : 'Live'"></span>
                </span>
            </div>
            <ul class="mt-4 space-y-4">
                <template x-for="log in items" :key="log.id">
                    <li class="activity-in flex gap-3">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-[#1a8a8e]"></span>
                        <div class="min-w-0">
                            <span class="ui-badge bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500 ring-slate-200" x-text="log.action"></span>
                            <p class="mt-1 text-sm text-slate-700" x-text="log.description"></p>
                            <p class="text-xs tabular-nums text-slate-400"><span x-text="log.user"></span> · <span x-text="log.created_at_human"></span></p>
                        </div>
                    </li>
                </template>
                <li x-show="items.length===0" class="py-6 text-center text-sm text-slate-500">No activity logged yet.</li>
            </ul>
        </section>
    </div>

    {{-- Quick actions --}}
    @if(auth()->user()?->canManageRecords())
        <section class="ui-card p-6">
            <h2 class="font-semibold text-slate-900">Quick Actions</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <a href="{{ route('events.create') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Create Event</a>
                <a href="{{ route('events') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Send Outreach</a>
                <a href="{{ route('incubatees', ['openOnboarding' => '1']) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Add Startup</a>
                <form method="POST" action="{{ route('google-drive.sync') }}">
                    @csrf
                    <button type="submit" class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-50">Sync Drive Storage</button>
                </form>
            </div>
        </section>
    @endif
</div>
@endsection
