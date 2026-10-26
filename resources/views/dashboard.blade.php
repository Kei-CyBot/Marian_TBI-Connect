@extends('layouts.app')
@section('title','Dashboard')
@section('eyebrow','Hybrid Hub · Overview')
@section('heading','Ecosystem Dashboard')
@section('subheading','Live snapshot across Internal Academic and External Industry tracks.')

@section('content')
@php
    $stats = [
        ['label'=>'Active Incubatees','all'=>$metrics['activeStartups']['all'],'internal'=>$metrics['activeStartups']['internal'],'external'=>$metrics['activeStartups']['external'],'delta'=>'Non-archived records'],
        ['label'=>'Applicants in Pipeline','all'=>$metrics['pendingIntakeApplications']['all'],'internal'=>$metrics['pendingIntakeApplications']['internal'],'external'=>$metrics['pendingIntakeApplications']['external'],'delta'=>'Awaiting review'],
        ['label'=>'Ecosystem Contacts','all'=>$metrics['verifiedContacts']['all'],'internal'=>$metrics['verifiedContacts']['internal'],'external'=>$metrics['verifiedContacts']['external'],'delta'=>'Verified contacts'],
        ['label'=>'Graduates & Alumni','all'=>$metrics['graduates']['all'],'internal'=>$metrics['graduates']['internal'],'external'=>$metrics['graduates']['external'],'delta'=>'Graduate + alumni'],
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
                <div class="mt-3 flex items-center gap-3 text-xs" :class="i===0 ? 'text-white/80' : 'text-gray-500'">
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

        {{-- Activity --}}
        <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="font-semibold">Recent Activity</h2>
            <ul class="mt-4 space-y-4">
                @forelse($metrics['recentActivity'] as $log)
                    <li class="flex gap-3">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-[#1a8a8e]"></span>
                        <div><p class="text-sm">{{ $log->description }}</p><p class="text-xs text-gray-400">{{ $log->created_at?->diffForHumans() }}</p></div>
                    </li>
                @empty
                    <li class="text-sm text-gray-500">No activity logged yet.</li>
                @endforelse
            </ul>
        </section>
    </div>

    {{-- Quick links --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <a href="{{ url('/technical-needs') }}" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition hover:border-[#b92369]/40">
            <p class="text-xs font-semibold uppercase tracking-wider text-[#b92369]">Technical Needs Due</p><p class="mt-1 text-2xl font-bold">7</p><p class="text-sm text-gray-500">Technical needs requiring follow-up</p></a>
        <a href="{{ url('/files') }}" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition hover:border-[#1a8a8e]/40">
            <p class="text-xs font-semibold uppercase tracking-wider text-[#1a8a8e]">Drive sync</p><p class="mt-1 text-2xl font-bold">Healthy</p><p class="text-sm text-gray-500">Google Drive synced 4 min ago</p></a>
        <a href="{{ url('/archives') }}" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition hover:border-gray-300">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Archive vault</p><p class="mt-1 text-2xl font-bold">11</p><p class="text-sm text-gray-500">Soft-deleted items awaiting audit</p></a>
    </div>
</div>
@endsection
