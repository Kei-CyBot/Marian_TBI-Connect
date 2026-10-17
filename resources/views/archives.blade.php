@extends('layouts.app')
@section('title','Archives')
@section('eyebrow','Directory Management')
@section('heading','Archives')
@section('subheading','Review and restore archived incubatees and contacts from one place.')

@section('content')
@if (session('archive_status'))
    <div role="status" class="mb-4 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('archive_status') }}</div>
@endif
<div x-data="{items:@js($items), kind:'All', track:'all', kinds:['All','Incubatee','Contact'], get list(){return this.items.filter(item => (this.kind==='All' || item.kind===this.kind) && (this.track==='all' || item.track===this.track))}}">
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="inline-flex rounded-2xl border border-slate-line bg-white p-1 text-sm font-semibold" role="group" aria-label="Filter archives by record type">
            <template x-for="itemKind in kinds" :key="itemKind">
                <button type="button" @click="kind=itemKind" class="rounded-xl px-3.5 py-1.5" :class="kind===itemKind ? 'bg-slate-ink text-white' : 'text-gray-600 hover:bg-gray-50'" x-text="itemKind"></button>
            </template>
        </div>
        <label class="sr-only" for="archive-track-filter">Filter by track</label>
        <select id="archive-track-filter" x-model="track" class="rounded-xl border border-slate-line bg-white px-3 py-2 text-sm">
            <option value="all">All tracks</option>
            <option value="internal">Internal Academic</option>
            <option value="external">External Industry</option>
        </select>
        <span class="text-sm text-gray-500"><span x-text="list.length"></span> archived record<span x-show="list.length!==1">s</span></span>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-100 bg-white shadow-sm">
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
                <template x-for="item in list" :key="item.kind+'-'+item.id">
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
        <div x-show="!list.length" x-cloak class="py-16 text-center text-sm text-gray-500">No archived records match these filters.</div>
    </div>
</div>
@endsection
