@extends('layouts.app')
@section('title','Technical Needs')
@section('eyebrow','Incubatee Support')
@section('heading','Technical Needs')
@section('subheading','Manage each incubatee’s equipment, services, subscriptions, owners, deadlines, and progress.')
@section('banner-actions')
    @if (auth()->user()->canManageRecords() && $selectedStartup)
        <button type="button" @click="$dispatch('open-technical-need')" class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg bg-[#9E2A5B] px-3.5 py-2 text-sm font-medium text-white shadow-2xs transition hover:bg-[#86224C]">+ Add Technical Need</button>
        <button type="button" @click="$dispatch('open-technical-approval-form')" class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-2xs transition hover:bg-slate-50">Technical Need Approval Form</button>
    @endif
@endsection

@section('content')
@if (session('technical_need_status'))
    <div role="status" class="mb-4 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('technical_need_status') }}</div>
@endif
@if ($errors->any())
    <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <p class="font-semibold">The technical need could not be saved:</p>
        <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div x-data="window.technicalNeedsPage(
        @js($requirements),
        @js($startups),
        @js((string) ($selectedStartup?->id ?? '')),
        @js($selectedStartup?->name ?? '')
     )"
     @open-technical-need.window="openCreate()"
     @edit-technical-need.window="openEdit($event.detail)"
     @review-technical-need.window="openReview($event.detail)"
     @open-technical-approval-form.window="openApprovalForm()"
     @keydown.escape.window="modal=false; reviewModal=false; approvalFormModal=false">

    @if (!$selectedStartup)
    <section class="mb-6" aria-labelledby="technical-need-folders-heading">
        <div class="mb-3">
            <div>
                <h2 id="technical-need-folders-heading" class="text-base font-semibold text-slate-900">Incubatee technical-needs folders</h2>
                <p class="mt-1 text-sm text-slate-600">Open an incubatee folder to view and manage its technical needs.</p>
            </div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <template x-for="startup in startups" :key="startup.id">
                <a :href="'{{ route('technical-needs') }}?startup_id='+startup.id" class="group flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-teal-300 hover:shadow-md">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-800 ring-1 ring-inset ring-teal-100" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75A1.5 1.5 0 0 1 5.25 5.25h4l1.5 1.5h8A1.5 1.5 0 0 1 20.25 8.25v9A1.5 1.5 0 0 1 18.75 18.75h-13.5a1.5 1.5 0 0 1-1.5-1.5v-10.5Z" /></svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-slate-900 group-hover:text-teal-900" x-text="startup.name"></span>
                        <span class="mt-0.5 block text-xs text-slate-600"><span x-text="startup.technicalNeedCount"></span> technical need<span x-show="startup.technicalNeedCount!==1">s</span></span>
                    </span>
                    <span class="text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-teal-700" aria-hidden="true">&rarr;</span>
                </a>
            </template>
        </div>
    </section>
    @else
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-teal-200 bg-teal-50/70 px-4 py-3">
        <div>
            <a href="{{ route('technical-needs') }}" class="text-sm font-semibold text-teal-900 underline decoration-teal-300 underline-offset-4 hover:text-teal-950">← Incubatee folders</a>
            <p class="mt-1 text-xs text-teal-800">Viewing the private technical-needs folder for {{ $selectedStartup->name }}.</p>
        </div>
        <div class="flex items-center gap-2">
            @if (auth()->user()->canManageRecords())
                <a href="{{ route('exports.technical-needs') }}" class="rounded-lg border border-teal-300 bg-white px-3 py-1.5 text-xs font-semibold text-teal-900 hover:bg-teal-50">Export to CSV</a>
            @endif
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-teal-900 ring-1 ring-inset ring-teal-200">{{ $selectedStartup->track === 'internal' ? 'Internal Academic' : 'External Industry' }}</span>
        </div>
    </div>

    @php
        $filterParameters = [
            'title', 'specifications', 'price_min', 'price_max', 'months_min', 'months_max',
            'responsible_team_member', 'date_entry_from', 'date_entry_to',
            'date_needed_from', 'date_needed_to', 'resource_tool_needed',
            'prospect_resource_person', 'priority', 'status', 'remarks',
        ];
        $hasFilters = collect($filterParameters)->contains(static fn (string $parameter): bool => request()->filled($parameter));
        $columns = [
            'title' => 'Title',
            'specifications' => 'Specifications',
            'price_subscription' => 'Price',
            'months_needed' => 'Months',
            'responsible_team_member' => 'Responsible',
            'date_entry' => 'Date Entry',
            'date_needed' => 'Date Needed',
            'resource_tool_needed' => 'Resource / Tool',
            'prospect_resource_person' => 'Prospect',
            'priority' => 'Priority',
            'remarks' => 'Remarks',
        ];
    @endphp

    <details class="mb-5 ui-card" @if ($hasFilters) open @endif>
        <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-4 py-3">
            <span class="text-sm font-semibold text-slate-900">Filter &amp; Search</span>
            <span class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium text-slate-500">{{ count($requirements) }} result{{ count($requirements) === 1 ? '' : 's' }}{{ $hasFilters ? ' · filters active' : '' }}</span>
                <label class="flex items-center gap-1.5">
                    <span class="text-xs font-semibold text-gray-600">Sort by</span>
                    <select form="technical-need-filters" name="sort_by" onchange="this.form.submit()" class="rounded-xl border border-slate-line bg-white px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                        @foreach ($columns as $key => $label)
                            <option value="{{ $key }}" @selected($sortBy === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <a href="{{ route('technical-needs', array_merge(request()->query(), ['startup_id' => $selectedStartup->id, 'sort_by' => $sortBy, 'sort_order' => $sortOrder === 'asc' ? 'desc' : 'asc'])) }}" class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" title="Toggle sort direction">
                    {{ $sortOrder === 'asc' ? '↑ Ascending' : '↓ Descending' }}
                </a>
            </span>
        </summary>
        <form id="technical-need-filters" method="GET" action="{{ route('technical-needs') }}" class="grid gap-3 border-t border-slate-100 px-4 py-4 sm:grid-cols-2 lg:grid-cols-4">
            <input type="hidden" name="startup_id" value="{{ $selectedStartup->id }}">
            <input type="hidden" name="sort_order" value="{{ $sortOrder }}">
            <label class="block"><span class="mb-1.5 block text-xs font-medium text-slate-700">Title</span><input name="title" value="{{ request('title') }}" maxlength="255" class="ui-input"></label>
            <label class="block"><span class="mb-1.5 block text-xs font-medium text-slate-700">Specifications</span><input name="specifications" value="{{ request('specifications') }}" maxlength="255" class="ui-input"></label>
            <label class="block"><span class="mb-1.5 block text-xs font-medium text-slate-700">Price min (PHP)</span><input name="price_min" type="number" min="0" step="0.01" value="{{ request('price_min') }}" class="ui-input"></label>
            <label class="block"><span class="mb-1.5 block text-xs font-medium text-slate-700">Price max (PHP)</span><input name="price_max" type="number" min="0" step="0.01" value="{{ request('price_max') }}" class="ui-input"></label>
            <label class="block"><span class="mb-1.5 block text-xs font-medium text-slate-700">Months min</span><input name="months_min" type="number" min="1" value="{{ request('months_min') }}" class="ui-input"></label>
            <label class="block"><span class="mb-1.5 block text-xs font-medium text-slate-700">Months max</span><input name="months_max" type="number" min="1" value="{{ request('months_max') }}" class="ui-input"></label>
            <label class="block"><span class="mb-1.5 block text-xs font-medium text-slate-700">Responsible team member</span>
                <select name="responsible_team_member" class="ui-select">
                    <option value="">All members</option>
                    @foreach ($responsibleOptions as $member)
                        <option value="{{ $member }}" @selected(request('responsible_team_member') === $member)>{{ $member }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block"><span class="mb-1.5 block text-xs font-medium text-slate-700">Date entry from</span><input name="date_entry_from" type="date" value="{{ request('date_entry_from') }}" class="ui-input"></label>
            <label class="block"><span class="mb-1.5 block text-xs font-medium text-slate-700">Date entry to</span><input name="date_entry_to" type="date" value="{{ request('date_entry_to') }}" class="ui-input"></label>
            <label class="block"><span class="mb-1.5 block text-xs font-medium text-slate-700">Date needed from</span><input name="date_needed_from" type="date" value="{{ request('date_needed_from') }}" class="ui-input"></label>
            <label class="block"><span class="mb-1.5 block text-xs font-medium text-slate-700">Date needed to</span><input name="date_needed_to" type="date" value="{{ request('date_needed_to') }}" class="ui-input"></label>
            <label class="block"><span class="mb-1.5 block text-xs font-medium text-slate-700">Resource / tool needed</span><input name="resource_tool_needed" value="{{ request('resource_tool_needed') }}" maxlength="255" class="ui-input"></label>
            <label class="block"><span class="mb-1.5 block text-xs font-medium text-slate-700">Prospect resource person</span><input name="prospect_resource_person" value="{{ request('prospect_resource_person') }}" maxlength="255" class="ui-input"></label>
            <label class="block"><span class="mb-1.5 block text-xs font-medium text-slate-700">Priority</span>
                <select name="priority" class="ui-select">
                    <option value="">All priorities</option>
                    @foreach (['Low', 'Moderate', 'High'] as $priority)
                        <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ $priority }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block"><span class="mb-1.5 block text-xs font-medium text-slate-700">Remarks</span><input name="remarks" value="{{ request('remarks') }}" maxlength="255" class="ui-input"></label>
            <label class="block"><span class="mb-1.5 block text-xs font-medium text-slate-700">Status</span>
                <select name="status" class="ui-select">
                    <option value="">All statuses</option>
                    @foreach (['pending', 'approved', 'rejected'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
                <button type="submit" class="rounded-xl bg-[#146e72] px-4 py-2 text-sm font-semibold text-white hover:bg-[#10595c]">Apply filters</button>
                <a href="{{ route('technical-needs', ['startup_id' => $selectedStartup->id]) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
            </div>
        </form>
    </details>

    <div class="space-y-3">
        <template x-for="item in filteredRequirements" :key="item.id">
            <article class="overflow-hidden ui-card">
                <div class="border-l-4 px-4 py-4 sm:px-5" :class="item.priority==='High' ? 'border-red-500' : item.priority==='Moderate' ? 'border-amber-400' : 'border-slate-300'">
                    <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-semibold text-slate-900" x-text="item.requirement"></h2>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1" :class="priorityClass(item.priority)" x-text="item.priority+' priority'"></span>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1" :class="statusClass(item.status)" x-text="statusLabel(item.status)"></span>
                            </div>
                            <p x-show="item.description" class="mt-1 line-clamp-2 text-sm text-slate-600" x-text="item.description"></p>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <a x-show="item.attachmentUrl" :href="item.attachmentUrl" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" :title="item.attachmentName">⬇ Download</a>
                            <a x-show="item.approvalUrl" :href="item.approvalUrl" target="_blank" rel="noopener" class="rounded-lg border border-teal-200 bg-teal-50 px-3 py-2 text-xs font-semibold text-teal-900 hover:bg-teal-100">👁️ View Approval</a>
                            @if (auth()->user()->canManageRecords() && $selectedStartup)
                                <button type="button" x-show="item.status==='pending'" @click="$dispatch('review-technical-need',item)" class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900 hover:bg-amber-100">Attach &amp; Review</button>
                                <button type="button" @click="$dispatch('edit-technical-need',item)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</button>
                                <form method="POST" :action="'{{ route('technical-needs') }}/'+item.id" @submit="if(!confirm('Remove this technical need?')) $event.preventDefault()">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <div class="mt-4 grid gap-3 border-t border-slate-100 pt-3 sm:grid-cols-3">
                        <div><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Estimated subscription</p><p class="mt-0.5 text-sm font-semibold text-slate-900" x-text="item.price ? '₱'+Number(item.price).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}) : 'Not specified'"></p></div>
                        <div><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Duration</p><p class="mt-0.5 text-sm text-slate-800" x-text="item.months ? item.months+' month(s)' : 'Not specified'"></p></div>
                        <div><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Date needed</p><p class="mt-0.5 text-sm text-slate-800" x-text="item.dateNeeded || 'Not specified'"></p></div>
                    </div>
                    <details class="group mt-3 border-t border-slate-100 pt-3">
                        <summary class="flex cursor-pointer list-none items-center justify-between text-xs font-semibold text-teal-800 hover:text-teal-950">
                            <span>More request details</span>
                            <span class="transition group-open:rotate-180" aria-hidden="true">⌄</span>
                        </summary>
                        <dl class="mt-3 grid gap-x-5 gap-y-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-xs font-medium text-slate-500">Responsible team member</dt><dd class="mt-0.5 text-slate-900" x-text="item.responsible || '—'"></dd></div>
                            <div><dt class="text-xs font-medium text-slate-500">Date entered</dt><dd class="mt-0.5 text-slate-900" x-text="item.dateEntry"></dd></div>
                            <div><dt class="text-xs font-medium text-slate-500">Resource / tool requested</dt><dd class="mt-0.5 text-slate-900" x-text="item.resource || '—'"></dd></div>
                            <div><dt class="text-xs font-medium text-slate-500">Prospective resource person</dt><dd class="mt-0.5 text-slate-900" x-text="item.prospect || '—'"></dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs font-medium text-slate-500">Remarks</dt><dd class="mt-0.5 whitespace-pre-wrap text-slate-900" x-text="item.remarks || '—'"></dd></div>
                        </dl>
                    </details>
                </div>
            </article>
        </template>
    </div>

    <div x-show="!filteredRequirements.length" x-cloak class="ui-empty mt-4">
        <p class="font-semibold text-slate-800">No requests match this folder and its filters.</p>
        <p class="mt-1">Clear the filters, or add an approved technical need.</p>
    </div>
    @endif

    @if (auth()->user()->canManageRecords() && $selectedStartup)
    <div x-show="modal" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="modal=false">
        <section role="dialog" aria-modal="true" aria-labelledby="technical-need-modal-title" class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-xl">
            <form method="POST" :action="editing ? '{{ route('technical-needs') }}/'+draft.id : '{{ route('technical-needs.store') }}'">
                @csrf
                <input type="hidden" name="_method" value="PUT" :disabled="!editing">
                <header class="flex items-start justify-between gap-4 bg-[#146e72] px-6 py-5 text-white">
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-white/80">Marian TBI incubatee support</p><h2 id="technical-need-modal-title" class="mt-1 text-lg font-bold" x-text="editing ? 'Edit Technical Need' : 'Add Technical Need'"></h2></div>
                    <button type="button" @click="modal=false" aria-label="Close form" class="rounded-lg p-1.5 text-white/90 hover:bg-white/15">✕</button>
                </header>
                <div class="grid gap-4 p-6 sm:grid-cols-2">
                    <input type="hidden" name="startup_id" x-model="draft.startupId">
                    <div class="sm:col-span-2 rounded-xl border border-teal-200 bg-teal-50/70 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-800">Incubatee folder</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900" x-text="selectedStartupName"></p>
                    </div>
                    <label class="sm:col-span-2"><span class="mb-1.5 block text-xs font-medium text-slate-700">Title <span class="text-red-600">*</span></span><input name="requirement" x-model="draft.requirement" required maxlength="255" class="ui-input"></label>
                    <label class="sm:col-span-2"><span class="mb-1.5 block text-xs font-medium text-slate-700">Specifications</span><textarea name="description" x-model="draft.description" rows="2" maxlength="5000" class="ui-input"></textarea></label>
                    <label><span class="mb-1.5 block text-xs font-medium text-slate-700">Price / subscription (PHP)</span><div class="flex rounded-xl border border-slate-line focus-within:border-[#1a8a8e]"><span class="px-3 py-2 text-sm font-semibold text-gray-700">₱</span><input name="subscription_price" type="number" min="0" step="0.01" x-model="draft.price" class="min-w-0 flex-1 rounded-r-xl border-0 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15"></div></label>
                    <label><span class="mb-1.5 block text-xs font-medium text-slate-700">Number of months needed</span><input name="months_needed" type="number" min="1" max="65535" x-model="draft.months" class="ui-input"></label>
                    <label><span class="mb-1.5 block text-xs font-medium text-slate-700">Responsible team member</span><input name="responsible_team_member" x-model="draft.responsible" maxlength="255" class="ui-input"></label>
                    <label><span class="mb-1.5 block text-xs font-medium text-slate-700">Date entry <span class="text-red-600">*</span></span><input name="date_entry" type="date" x-model="draft.dateEntry" required class="ui-input"></label>
                    <label><span class="mb-1.5 block text-xs font-medium text-slate-700">Date needed</span><input name="date_needed" type="date" x-model="draft.dateNeeded" :min="draft.dateEntry" class="ui-input"></label>
                    <label><span class="mb-1.5 block text-xs font-medium text-slate-700">Resource / tool needed</span><input name="resource_tool" x-model="draft.resource" maxlength="255" class="ui-input"></label>
                    <label><span class="mb-1.5 block text-xs font-medium text-slate-700">Prospect resource person</span><input name="prospect_resource_person" x-model="draft.prospect" maxlength="255" class="ui-input"></label>
                    <label><span class="mb-1.5 block text-xs font-medium text-slate-700">Priority</span><select name="priority" x-model="draft.priority" required class="ui-select"><option>Low</option><option>Moderate</option><option>High</option></select></label>
                    <label class="sm:col-span-2"><span class="mb-1.5 block text-xs font-medium text-slate-700">Remarks</span><textarea name="remarks" x-model="draft.remarks" rows="2" maxlength="5000" class="ui-input"></textarea></label>
                </div>
                <footer class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4">
                    <button type="button" @click="modal=false" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-xl bg-[#146e72] px-5 py-2 text-sm font-semibold text-white hover:bg-[#10595c]" x-text="editing ? 'Save changes' : 'Submit for review'"></button>
                </footer>
            </form>
        </section>
    </div>

    <div x-show="reviewModal" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="reviewModal=false">
        <section role="dialog" aria-modal="true" aria-labelledby="technical-need-review-title" class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-xl">
            <form method="POST" enctype="multipart/form-data" :action="'{{ route('technical-needs') }}/'+reviewItem.id+'/review'">
                @csrf
                <input type="hidden" name="action" x-model="reviewAction">
                <header class="flex items-start justify-between gap-4 bg-[#146e72] px-6 py-5 text-white">
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-white/80">Technical need review</p><h2 id="technical-need-review-title" class="mt-1 text-lg font-bold">Attach &amp; Review</h2></div>
                    <button type="button" @click="reviewModal=false" aria-label="Close review" class="rounded-lg p-1.5 text-white/90 hover:bg-white/15">✕</button>
                </header>
                <div class="space-y-4 p-6">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-slate-900" x-text="reviewItem.requirement"></p>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1" :class="priorityClass(reviewItem.priority)" x-text="reviewItem.priority+' priority'"></span>
                        </div>
                        <p class="mt-1 text-xs text-slate-600"><span x-text="reviewItem.startupName"></span> · Requested by <span x-text="reviewItem.requestor || 'Unknown'"></span></p>
                        <p x-show="reviewItem.description" class="mt-2 whitespace-pre-wrap text-sm text-slate-700" x-text="reviewItem.description"></p>
                        <span class="mt-3 inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1" :class="statusClass(reviewItem.status)" x-text="statusLabel(reviewItem.status)"></span>
                    </div>
                    <label class="block">
                        <span class="mb-1.5 block text-xs font-medium text-slate-700">Attach Institutional Approval Document (PDF, Word, Excel, max 10 MB)</span>
                        <input name="approval_document" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" class="block ui-select text-slate-800 file:mr-3 file:rounded-lg file:border-0 file:bg-teal-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-teal-900">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-xs font-medium text-slate-700">Review notes</span>
                        <textarea name="review_notes" rows="3" maxlength="5000" class="ui-input"></textarea>
                    </label>
                </div>
                <footer class="flex flex-wrap justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4">
                    <button type="button" @click="reviewModal=false" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" @click="reviewAction='rejected'" class="rounded-xl bg-red-600 px-5 py-2 text-sm font-semibold text-white hover:bg-red-700">Reject</button>
                    <button type="submit" @click="reviewAction='approved'" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve</button>
                </footer>
            </form>
        </section>
    </div>

    <div x-show="approvalFormModal" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="approvalFormModal=false">
        <section role="dialog" aria-modal="true" aria-labelledby="approval-form-title" class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-xl">
            <form @submit.prevent="printApprovalForm()">
                <header class="flex items-start justify-between gap-4 bg-[#146e72] px-6 py-5 text-white">
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-white/80">Marian TBI institutional template</p><h2 id="approval-form-title" class="mt-1 text-lg font-bold">Technical Need Approval Form</h2></div>
                    <button type="button" @click="approvalFormModal=false" aria-label="Close approval form" class="rounded-lg p-1.5 text-white/90 hover:bg-white/15">✕</button>
                </header>
                <div class="grid gap-4 p-6 sm:grid-cols-2">
                    <label class="sm:col-span-2"><span class="mb-1.5 block text-xs font-medium text-slate-700">Incubatee</span><input x-model="approvalDraft.incubatee" readonly class="w-full rounded-xl border border-slate-line bg-slate-50 px-3 py-2 text-sm"></label>
                    <label class="sm:col-span-2"><span class="mb-1.5 block text-xs font-medium text-slate-700">Technical need / subscription</span><input x-model="approvalDraft.requirement" required maxlength="255" class="ui-select"></label>
                    <label class="sm:col-span-2"><span class="mb-1.5 block text-xs font-medium text-slate-700">Specifications / purpose</span><textarea x-model="approvalDraft.description" rows="3" maxlength="5000" class="ui-select"></textarea></label>
                    <label><span class="mb-1.5 block text-xs font-medium text-slate-700">Estimated cost (PHP)</span><input x-model="approvalDraft.price" type="number" min="0" step="0.01" class="ui-select"></label>
                    <label><span class="mb-1.5 block text-xs font-medium text-slate-700">Date needed</span><input x-model="approvalDraft.dateNeeded" type="date" class="ui-select"></label>
                    <label><span class="mb-1.5 block text-xs font-medium text-slate-700">Priority</span><select x-model="approvalDraft.priority" class="ui-select"><option>Low</option><option>Moderate</option><option>High</option></select></label>
                    <label class="sm:col-span-2"><span class="mb-1.5 block text-xs font-medium text-slate-700">Remarks</span><textarea x-model="approvalDraft.remarks" rows="2" maxlength="5000" class="ui-select"></textarea></label>
                </div>
                <footer class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4">
                    <button type="button" @click="approvalFormModal=false" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-xl bg-[#146e72] px-5 py-2 text-sm font-semibold text-white hover:bg-[#10595c]">Preview &amp; Print</button>
                </footer>
            </form>
        </section>
    </div>
    @endif
</div>
@endsection
