@extends('layouts.app')
@section('title','Events & Outreach')
@section('eyebrow','Events, Workshops & Mass Outreach')
@section('heading','Events & Outreach')
@section('subheading','Internal academic events, external industry events, and hybrid public events — with RSVP tracking and email broadcasts.')
@section('banner-actions')
    @if (auth()->user()->canManageRecords())
        <button @click="$dispatch('open-broadcast')" class="rounded-xl bg-white/15 px-4 py-2 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/25">✉️ Email Contacts</button>
        <button @click="$dispatch('open-event-form')" class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-[#b92369] shadow-sm transition hover:bg-[#fcf0f5]">+ Create Event</button>
    @endif
@endsection

@section('content')
@if (session('email_status'))
    <div role="status" class="mb-4 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('email_status') }}</div>
@endif
@if (session('email_error'))
    <div role="alert" class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">{{ session('email_error') }}</div>
@endif
@if ($errors->any())
    <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <p class="font-semibold">The email could not be sent:</p>
        <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
@php
    $events = [
        ['id'=>1,'title'=>'Campus Hackathon 2026','scope'=>'internal','kind'=>'Campus Hackathon','date'=>'2026-10-17','time'=>'09:00 AM','loc'=>'Innovation Hub B, Rm 2','virtual'=>false,'host'=>'Innovation Office','cohort'=>'Student Founders','rsvp'=>[86,24,9]],
        ['id'=>2,'title'=>'TTO Commercialization Workshop','scope'=>'internal','kind'=>'TTO Workshop','date'=>'2026-10-21','time'=>'02:00 PM','loc'=>'Science Bldg, Rm 101','virtual'=>false,'host'=>'Tech Transfer Office','cohort'=>'Faculty Founders','rsvp'=>[34,12,3]],
        ['id'=>3,'title'=>'VC Investor Pitch Night','scope'=>'external','kind'=>'VC Pitch Night','date'=>'2026-10-24','time'=>'06:00 PM','loc'=>'https://zoom.us/j/000-000-0000','virtual'=>true,'host'=>'Meridian Ventures','cohort'=>'Seed-stage Startups','rsvp'=>[42,15,6]],
        ['id'=>4,'title'=>'Student Founder Bootcamp','scope'=>'internal','kind'=>'Founder Bootcamp','date'=>'2026-10-28','time'=>'10:00 AM','loc'=>'Innovation Hub A','virtual'=>false,'host'=>'Student Affairs','cohort'=>'Applicants','rsvp'=>[58,20,4]],
        ['id'=>5,'title'=>'Corporate Partner Matchmaking','scope'=>'external','kind'=>'Partner Matchmaking','date'=>'2026-11-04','time'=>'01:00 PM','loc'=>'Business Center, Hall C','virtual'=>false,'host'=>'Partnerships Desk','cohort'=>'Active Incubatees','rsvp'=>[27,18,5]],
        ['id'=>6,'title'=>'Open Innovation Expo','scope'=>'hybrid','kind'=>'Open Innovation Expo','date'=>'2026-11-12','time'=>'09:00 AM','loc'=>'Convention Center + Livestream','virtual'=>true,'host'=>'Marian TBI Connect','cohort'=>'Public / All','rsvp'=>[210,96,18]],
        ['id'=>7,'title'=>'Regional Startup Summit','scope'=>'external','kind'=>'Startup Summit','date'=>'2026-11-19','time'=>'08:30 AM','loc'=>'Regional Trade Hall','virtual'=>false,'host'=>'Regional Dev. Council','cohort'=>'Regional Startups','rsvp'=>[132,40,11]],
        ['id'=>8,'title'=>'Public Demo Day','scope'=>'hybrid','kind'=>'Public Demo Day','date'=>'2026-12-03','time'=>'03:00 PM','loc'=>'Main Auditorium + Zoom','virtual'=>true,'host'=>'Marian TBI Connect','cohort'=>'Graduating Cohort','rsvp'=>[150,64,9]],
    ];
@endphp

<div x-data="{
        events:@js($events), view:'list', menu:null, selectedEvent:null,
        cursor:new Date(new Date().getFullYear(),new Date().getMonth(),1),
        filt:'all',
        saveEvent(data){
            const event={id:data.id || Math.max(0,...this.events.map(item=>item.id))+1,title:data.title,scope:data.scope,kind:data.kind,date:data.date,time:data.time,loc:data.loc,virtual:data.virtual,host:data.host,cohort:data.cohort,rsvp:[0,0,0]};
            const index=this.events.findIndex(item=>item.id===event.id);
            if(index<0) this.events.unshift(event);
            else this.events.splice(index,1,event);
            this.selectedEvent=event;
        },
        get list(){ return this.events.filter(e => (scope==='all' || e.scope===scope || e.scope==='hybrid') && (this.filt==='all' || e.scope===this.filt)) },
        get monthLabel(){ return this.cursor.toLocaleString('en-US',{month:'long',year:'numeric'}) },
        get cells(){
            const y=this.cursor.getFullYear(), m=this.cursor.getMonth();
            const first=new Date(y,m,1).getDay(), days=new Date(y,m+1,0).getDate(), out=[];
            for(let i=0;i<first;i++) out.push(null);
            for(let d=1;d<=days;d++){
                const iso=`${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                out.push({d, iso, ev:this.list.filter(e=>e.date===iso)});
            }
            while(out.length<42) out.push(null);
            return out;
        },
        shift(n){ this.cursor=new Date(this.cursor.getFullYear(),this.cursor.getMonth()+n,1) },
        todayIso(){
            const today=new Date();
            return `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
        },
        fmt(iso){ const d=new Date(iso+'T00:00:00'); return d.toLocaleString('en-US',{month:'short',day:'numeric'}) },
        pill(s){ return s==='internal' ? 'bg-[#fcf0f5] text-[#b92369]' : s==='external' ? 'bg-[#eaf6f6] text-[#1a8a8e]' : 'grad text-white' },
        bar(s){ return s==='internal' ? 'bg-[#b92369]' : s==='external' ? 'bg-[#1a8a8e]' : 'grad' },
        calendarChip(s){ return s==='internal' ? 'bg-[#b92369] text-white' : s==='external' ? 'bg-[#1a8a8e] text-white' : 'grad text-white' },
        lbl(s){ return s==='internal' ? 'Internal Academic' : s==='external' ? 'External Industry' : 'Hybrid · Public' }
     }" @click.window="menu=null" @event-form-save.window="saveEvent($event.detail)" @keydown.escape.window="selectedEvent=null">

    {{-- Toolbar --}}
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <div class="inline-flex rounded-2xl border border-slate-line bg-white p-1 text-sm font-semibold">
            <button @click="filt='all'" class="rounded-xl px-3.5 py-1.5" :class="filt==='all' ? 'bg-slate-ink text-white' : 'text-gray-600 hover:bg-gray-50'">All</button>
            <button @click="filt='internal'" class="rounded-xl px-3.5 py-1.5" :class="filt==='internal' ? 'bg-[#b92369] text-white' : 'text-gray-600 hover:bg-gray-50'">Internal</button>
            <button @click="filt='external'" class="rounded-xl px-3.5 py-1.5" :class="filt==='external' ? 'bg-[#1a8a8e] text-white' : 'text-gray-600 hover:bg-gray-50'">External</button>
            <button @click="filt='hybrid'" class="rounded-xl px-3.5 py-1.5" :class="filt==='hybrid' ? 'grad text-white' : 'text-gray-600 hover:bg-gray-50'">Hybrid</button>
        </div>
        <span class="text-sm text-gray-500"><span x-text="list.length"></span> events</span>
        <div class="ml-auto inline-flex rounded-xl border border-slate-line bg-white p-0.5 text-sm font-medium">
            <button type="button" @click="view='list'" :aria-pressed="view==='list'" class="rounded-lg px-3 py-1.5" :class="view==='list' ? 'bg-[#b92369] text-white' : 'text-gray-600'">List Cards View</button>
            <button type="button" @click="view='calendar'; selectedEvent=null" :aria-pressed="view==='calendar'" class="rounded-lg px-3 py-1.5" :class="view==='calendar' ? 'bg-[#1a8a8e] text-white' : 'text-gray-600'">Interactive Monthly Calendar View</button>
        </div>
    </div>

    {{-- LIST VIEW --}}
    <div x-show="view==='list'" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <template x-for="e in list" :key="e.id">
            <article class="relative flex flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="h-1.5" :class="bar(e.scope)"></div>
                <div class="flex-1 p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex flex-wrap gap-1.5">
                            <span class="rounded-lg px-2 py-0.5 text-[11px] font-semibold" :class="pill(e.scope)" x-text="lbl(e.scope)"></span>
                            <span class="rounded-lg bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600" x-text="e.kind"></span>
                        </div>
                        {{-- Action menu --}}
                        <div class="relative">
                            @if (auth()->user()->canManageRecords())
                                <button @click.stop="menu = menu===e.id ? null : e.id" class="rounded-lg px-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700">⋯</button>
                                <div x-show="menu===e.id" x-cloak x-transition @click.stop class="absolute right-0 z-10 mt-1 w-44 rounded-2xl border border-gray-200 bg-white p-1.5 text-sm shadow-xl">
                                    <button @click="$dispatch('open-event-form',e); menu=null" class="block w-full rounded-lg px-2.5 py-1.5 text-left hover:bg-gray-50">Edit event</button>
                                    <button class="block w-full rounded-lg px-2.5 py-1.5 text-left hover:bg-gray-50">Manage RSVPs</button>
                                    <button @click="$dispatch('open-broadcast',e); menu=null" class="block w-full rounded-lg px-2.5 py-1.5 text-left font-semibold text-[#1a8a8e] hover:bg-[#eaf6f6]">Email contacts</button>
                                </div>
                            @endif
                        </div>
                    </div>

                    <h3 class="mt-3 font-semibold leading-snug" x-text="e.title"></h3>

                    <div class="mt-3 inline-flex items-center gap-2 rounded-xl bg-gray-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700">
                        📅 <span x-text="fmt(e.date)"></span> · <span x-text="e.time"></span>
                    </div>

                    <dl class="mt-3 space-y-1.5 text-xs text-gray-600">
                        <div class="flex gap-2"><dt class="w-14 text-gray-400">Where</dt>
                            <dd class="min-w-0 flex-1 truncate">
                                <span x-show="!e.virtual" x-text="'📍 '+e.loc"></span>
                                <a x-show="e.virtual" :href="e.loc.startsWith('http') ? e.loc : '#'" class="font-medium text-[#1a8a8e] hover:underline" x-text="e.loc.startsWith('http') ? '🎥 Virtual · Zoom link' : '🎥 '+e.loc"></a>
                            </dd></div>
                        <div class="flex gap-2"><dt class="w-14 text-gray-400">Host</dt><dd class="flex-1 truncate" x-text="e.host"></dd></div>
                        <div class="flex gap-2"><dt class="w-14 text-gray-400">Audience</dt><dd class="flex-1"><span class="rounded-md bg-gray-100 px-1.5 py-0.5 font-medium" x-text="e.cohort"></span></dd></div>
                    </dl>
                </div>

                {{-- RSVP counter --}}
                <div class="border-t border-gray-100 bg-gray-50/70 px-5 py-3">
                    <div class="flex h-2 overflow-hidden rounded-full bg-gray-200">
                        <div class="bg-[#1a8a8e]" :style="`width:${e.rsvp[0]/(e.rsvp[0]+e.rsvp[1]+e.rsvp[2])*100}%`"></div>
                        <div class="bg-amber-300" :style="`width:${e.rsvp[1]/(e.rsvp[0]+e.rsvp[1]+e.rsvp[2])*100}%`"></div>
                        <div class="bg-[#b92369]" :style="`width:${e.rsvp[2]/(e.rsvp[0]+e.rsvp[1]+e.rsvp[2])*100}%`"></div>
                    </div>
                    <div class="mt-2 flex justify-between text-[11px] font-medium">
                        <span class="text-[#1a8a8e]">● <span x-text="e.rsvp[0]"></span> Attending</span>
                        <span class="text-amber-600">● <span x-text="e.rsvp[1]"></span> Pending</span>
                        <span class="text-[#b92369]">● <span x-text="e.rsvp[2]"></span> Declined</span>
                    </div>
                </div>
            </article>
        </template>
    </div>

    {{-- CALENDAR VIEW --}}
    <div x-show="view==='calendar'" x-cloak class="w-full overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Monthly calendar</p>
                <h2 class="mt-0.5 text-lg font-bold text-slate-800" x-text="monthLabel"></h2>
            </div>
            <div class="flex items-center gap-1">
                <button type="button" @click="shift(-1); selectedEvent=null" aria-label="Previous month" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm hover:bg-gray-50">‹</button>
                <button type="button" @click="cursor=new Date(new Date().getFullYear(),new Date().getMonth(),1); selectedEvent=null" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium hover:bg-gray-50">Today</button>
                <button type="button" @click="shift(1); selectedEvent=null" aria-label="Next month" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm hover:bg-gray-50">›</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <div class="min-w-[840px]">
                <div class="grid grid-cols-7 border-b border-gray-100 bg-gray-50 text-center text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                    <template x-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']"><div class="py-3" x-text="d"></div></template>
                </div>
                <div class="grid grid-cols-7">
                    <template x-for="(c,i) in cells" :key="i">
                        <div class="min-h-[132px] border-b border-r border-gray-100 p-2" :class="c ? (c.iso===todayIso() ? 'bg-[#fcf0f5]/40' : 'bg-white') : 'bg-gray-50/60'">
                            <template x-if="c">
                                <div>
                                    <p class="inline-flex h-7 min-w-7 items-center justify-center rounded-full px-1 text-xs font-semibold"
                                       :class="c.iso===todayIso() ? 'bg-[#b92369] text-white' : 'text-gray-500'"
                                       x-text="c.d"></p>
                                    <div class="mt-1.5 space-y-1">
                                        <template x-for="e in c.ev" :key="e.id">
                                            <button type="button" @click.stop="selectedEvent=e"
                                                    :aria-label="'View details for '+e.title"
                                                    class="block w-full truncate rounded-md px-2 py-1 text-left text-[10px] font-semibold shadow-sm transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-slate-400"
                                                    :class="calendarChip(e.scope)" x-text="e.time+' · '+e.title"></button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap gap-x-5 gap-y-2 border-t border-gray-100 px-5 py-3 text-xs text-gray-600">
            <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-sm bg-[#b92369]"></span>Internal</span>
            <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-sm bg-[#1a8a8e]"></span>External</span>
            <span class="flex items-center gap-2"><span class="grad h-3 w-3 rounded-sm"></span>Hybrid</span>
        </div>
        <div x-show="selectedEvent" x-cloak x-transition.opacity class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="selectedEvent=null">
            <template x-if="selectedEvent">
                <section role="dialog" aria-modal="true" aria-labelledby="calendar-event-title" class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <header class="flex items-start justify-between gap-4 px-6 py-5 text-white" :class="bar(selectedEvent.scope)">
                        <div>
                            <span class="inline-flex rounded-lg bg-white/20 px-2.5 py-1 text-xs font-semibold" x-text="lbl(selectedEvent.scope)"></span>
                            <h3 id="calendar-event-title" class="mt-2 text-lg font-bold" x-text="selectedEvent.title"></h3>
                        </div>
                        <button type="button" @click="selectedEvent=null" aria-label="Close event details" class="rounded-lg p-1.5 text-white/90 hover:bg-white/15">✕</button>
                    </header>
                    <dl class="space-y-3 px-6 py-5 text-sm">
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Event type</dt><dd class="mt-1 font-medium text-slate-800" x-text="selectedEvent.kind"></dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Date and time</dt><dd class="mt-1 text-slate-700" x-text="fmt(selectedEvent.date)+' · '+selectedEvent.time"></dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Location</dt><dd class="mt-1 break-words text-slate-700" x-text="selectedEvent.loc"></dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Host</dt><dd class="mt-1 text-slate-700" x-text="selectedEvent.host"></dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Target audience</dt><dd class="mt-1 text-slate-700" x-text="selectedEvent.cohort"></dd></div>
                    </dl>
                    <footer class="flex flex-wrap justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4">
                        @if (auth()->user()->canManageRecords())
                            <button type="button" @click="$dispatch('open-event-form',selectedEvent)" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-100">Edit event</button>
                            <button type="button" @click="$dispatch('open-broadcast',selectedEvent); selectedEvent=null" class="rounded-lg bg-[#1a8a8e] px-3 py-2 text-xs font-semibold text-white hover:bg-[#146e72]">Email contacts</button>
                        @endif
                    </footer>
                </section>
            </template>
        </div>
    </div>

    <div x-show="!list.length" x-cloak class="rounded-2xl border border-dashed border-gray-300 bg-white py-16 text-center text-sm text-gray-500">No events match this scope.</div>
</div>
@include('partials.broadcast-modal')
@endsection
