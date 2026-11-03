@extends('layouts.app')
@section('title','Archives')
@section('eyebrow','Directory Management')
@section('heading','Archives')
@section('subheading','Review, restore, and browse archived events, incubatees, and contacts from one place.')

@section('content')
@if (session('archive_status'))
    <div role="status" class="mb-4 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('archive_status') }}</div>
@endif
<div x-data="{
        items:@js($items),
        events:@js($events),
        tab:@js($initialTab),
        track:'all',
        tabs:['All','Events','Incubatees','Contacts'],
        get directory(){
            return this.items.filter(item =>
                (this.tab==='All'
                    || (this.tab==='Incubatees' && item.kind==='Incubatee')
                    || (this.tab==='Contacts' && item.kind==='Contact'))
                && (this.track==='all' || item.track===this.track));
        },
        get counts(){
            return {
                All: this.events.length + this.items.length,
                Events: this.events.length,
                Incubatees: this.items.filter(item => item.kind==='Incubatee').length,
                Contacts: this.items.filter(item => item.kind==='Contact').length,
            };
        },
        get visibleCount(){
            return this.tab==='Events' ? this.events.length : (this.tab==='All' ? this.events.length + this.directory.length : this.directory.length);
        }
     }">
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="inline-flex flex-wrap rounded-2xl border border-slate-line bg-white p-1 text-sm font-semibold" role="group" aria-label="Filter archives by record type">
            <template x-for="itemTab in tabs" :key="itemTab">
                <button type="button" @click="tab=itemTab" class="rounded-xl px-3.5 py-1.5" :class="tab===itemTab ? 'bg-slate-ink text-white' : 'text-gray-600 hover:bg-gray-50'">
                    <span x-text="itemTab"></span>
                    <span class="opacity-70" x-text="'('+counts[itemTab]+')'"></span>
                </button>
            </template>
        </div>
        <label class="sr-only" for="archive-track-filter">Filter by track</label>
        <select id="archive-track-filter" x-show="tab!=='Events'" x-model="track" class="rounded-xl border border-slate-line bg-white px-3 py-2 text-sm">
            <option value="all">All tracks</option>
            <option value="internal">Internal Academic</option>
            <option value="external">External Industry</option>
        </select>
        <span class="text-sm text-gray-500">
            <span x-text="visibleCount"></span>
            archived record<span x-show="visibleCount!==1">s</span>
        </span>
    </div>

    <div x-show="tab==='All' || tab==='Events'" class="mb-6 overflow-x-auto rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-800">Event Archives</h2>
            <p class="text-xs text-gray-500">Completed and cancelled events, including post-event summaries.</p>
        </div>
        <table class="min-w-[1000px] w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th scope="col" class="px-4 py-3">Event</th>
                    <th scope="col" class="px-4 py-3">Final status</th>
                    <th scope="col" class="px-4 py-3">Date</th>
                    <th scope="col" class="px-4 py-3">Turnout</th>
                    <th scope="col" class="px-4 py-3">Cancellation reason</th>
                    <th scope="col" class="px-4 py-3">Post-event notes</th>
                    <th scope="col" class="px-4 py-3">Archived</th>
                    <th scope="col" class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <template x-for="event in events" :key="event.id">
                    <tr class="hover:bg-gray-50/70">
                        <th scope="row" class="px-4 py-3 font-semibold text-slate-800" x-text="event.title"></th>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="event.status==='completed' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'" x-text="event.statusLabel"></span>
                        </td>
                        <td class="px-4 py-3 text-gray-600" x-text="event.date"></td>
                        <td class="px-4 py-3 text-gray-600" x-text="event.turnout===null ? '—' : event.turnout+'%'"></td>
                        <td class="max-w-[220px] px-4 py-3 text-gray-600" x-text="event.reason"></td>
                        <td class="max-w-[260px] px-4 py-3 text-gray-600" x-text="event.notes"></td>
                        <td class="px-4 py-3 text-gray-600" x-text="event.archivedAt"></td>
                        <td class="px-4 py-3">
                            @if (auth()->user()->canManageRecords())
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a :href="event.summaryUrl" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-[#146e72] hover:bg-[#eaf6f6]">👁️ View Summary</a>
                                    <a x-show="event.documentUrl" :href="event.documentUrl" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-gray-100">📄 View Attached Documents</a>
                                    <form method="POST" :action="event.restoreUrl">
                                        @csrf
                                        <button type="submit" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-[#b92369] hover:bg-[#fcf0f5]">🔄 Unarchive / Restore</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <div x-show="!events.length" x-cloak class="py-16 text-center text-sm text-gray-500">No archived events yet.</div>
    </div>

    <div x-show="tab!=='Events'" class="overflow-x-auto rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-800">Directory Archives</h2>
            <p class="text-xs text-gray-500">Archived incubatees and contacts.</p>
        </div>
        <table class="min-w-[900px] w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th scope="col" class="px-4 py-3">Record</th>
                    <th scope="col" class="px-4 py-3">Type</th>
                    <th scope="col" class="px-4 py-3">Track</th>
                    <th scope="col" class="px-4 py-3">Archived</th>
                    <th scope="col" class="px-4 py-3">Archived by</th>
                    <th scope="col" class="px-4 py-3">Reason</th>
                    <th scope="col" class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <template x-for="item in directory" :key="item.kind+'-'+item.id">
                    <tr class="hover:bg-gray-50/70">
                        <th scope="row" class="px-4 py-3 font-semibold text-slate-800" x-text="item.name"></th>
                        <td class="px-4 py-3 text-gray-600" x-text="item.kind"></td>
                        <td class="px-4 py-3">
                            <span class="rounded-lg px-2 py-0.5 text-xs font-semibold" :class="item.track==='internal' ? 'bg-[#fcf0f5] text-[#b92369]' : 'bg-[#eaf6f6] text-[#1a8a8e]'" x-text="item.track==='internal' ? 'Internal Academic' : 'External Industry'"></span>
                        </td>
                        <td class="px-4 py-3 text-gray-600" x-text="item.archivedAt"></td>
                        <td class="px-4 py-3 text-gray-600" x-text="item.archivedBy"></td>
                        <td class="px-4 py-3 text-gray-600" x-text="item.reason"></td>
                        <td class="px-4 py-3">
                            @if (auth()->user()->canManageRecords())
                                <div class="flex justify-end gap-2">
                                    <form method="POST" :action="item.restoreUrl">
                                        @csrf
                                        <button type="submit" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-[#146e72] hover:bg-[#eaf6f6]">Restore</button>
                                    </form>
                                    <form method="POST" :action="item.deleteUrl" onsubmit="return confirm('Permanently delete this record and its related startup data? This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-[#b92369] hover:bg-[#fcf0f5]">Delete permanently</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <div x-show="!directory.length" x-cloak class="py-16 text-center text-sm text-gray-500">No archived records match these filters.</div>
    </div>
</div>
@endsection
