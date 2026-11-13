@php
    $event = $event ?? null;
    $types = [
        'internal' => ['Campus Hackathon', 'TTO Commercialization Workshop', 'Student Founder Bootcamp', 'Internal Demo Day'],
        'external' => ['VC Investor Pitch Night', 'Corporate Partner Matchmaking', 'Regional Startup Summit', 'Industry Masterclass'],
        'hybrid' => ['Open Innovation Expo', 'Public Demo Day'],
    ];
    $allTypes = array_merge(...array_values($types));
    $kind = old('kind', $event->kind ?? '');
    $knownKind = in_array($kind, $allTypes, true);
    $host = old('host', $event->host ?? 'Marian TBI');
    $knownHost = $host === 'Marian TBI' || in_array($host, $hosts, true);

    $formValues = [
        'title' => old('title', $event->title ?? ''),
        'scope' => old('scope', $event->scope ?? 'internal'),
        'kind' => $knownKind ? $kind : ($kind !== '' ? '__other__' : ''),
        'kindOther' => $knownKind ? '' : $kind,
        'startsAt' => old('starts_at', $event?->starts_at?->format('Y-m-d\TH:i') ?? ''),
        'endsAt' => old('ends_at', $event?->ends_at?->format('Y-m-d\TH:i') ?? ''),
        'virtual' => (bool) old('virtual', $event->virtual ?? false),
        'location' => old('location', $event->location ?? ''),
        'host' => $knownHost ? $host : '__other__',
        'hostOther' => $knownHost ? '' : $host,
    ];
@endphp

<div x-data="{
        types:@js($types),
        hosts:@js($hosts),
        f:@js($formValues),
        get kindOptions(){ return this.types[this.f.scope] || [] },
        chooseScope(scope){ this.f.scope=scope; this.f.kind=''; this.f.kindOther='' }
     }">
    <form method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif

        <div>
            <label for="event-title" class="mb-1 block text-xs font-semibold text-slate-600">Event name <span class="text-red-500">*</span></label>
            <input id="event-title" name="title" x-model="f.title" required maxlength="255" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
        </div>

        <div>
            <span class="mb-1 block text-xs font-semibold text-slate-600">Scope</span>
            <div class="inline-flex rounded-xl border border-slate-line bg-white p-0.5 text-xs font-semibold">
                <button type="button" @click="chooseScope('internal')" class="rounded-lg px-3 py-1.5" :class="f.scope==='internal' ? 'bg-[#b92369] text-white' : 'text-gray-600'">Internal</button>
                <button type="button" @click="chooseScope('external')" class="rounded-lg px-3 py-1.5" :class="f.scope==='external' ? 'bg-[#1a8a8e] text-white' : 'text-gray-600'">External</button>
                <button type="button" @click="chooseScope('hybrid')" class="rounded-lg px-3 py-1.5" :class="f.scope==='hybrid' ? 'grad text-white' : 'text-gray-600'">Hybrid</button>
            </div>
            <input type="hidden" name="scope" :value="f.scope">
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="event-kind" class="mb-1 block text-xs font-semibold text-slate-600">Event type <span class="text-red-500">*</span></label>
                <select id="event-kind" name="kind" x-model="f.kind" required class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
                    <option value="">Select…</option>
                    <template x-for="type in kindOptions" :key="type">
                        <option :value="type" x-text="type"></option>
                    </template>
                    <option value="__other__">Other</option>
                </select>
            </div>
            <div x-show="f.kind==='__other__'" x-cloak>
                <label for="event-kind-other" class="mb-1 block text-xs font-semibold text-slate-600">Specify event type <span class="text-red-500">*</span></label>
                <input id="event-kind-other" name="kind_other" x-model="f.kindOther" maxlength="120" :required="f.kind==='__other__'" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="event-starts-at" class="mb-1 block text-xs font-semibold text-slate-600">Starts at <span class="text-red-500">*</span></label>
                <input id="event-starts-at" type="datetime-local" name="starts_at" x-model="f.startsAt" step="60" required class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                <p class="mt-1 text-xs text-gray-500">24-hour time, e.g. 2026-11-02 14:30.</p>
            </div>
            <div>
                <label for="event-ends-at" class="mb-1 block text-xs font-semibold text-slate-600">Ends at <span class="text-red-500">*</span></label>
                <input id="event-ends-at" type="datetime-local" name="ends_at" x-model="f.endsAt" step="60" required class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                <p class="mt-1 text-xs text-gray-500">Must be after the start time.</p>
            </div>
        </div>

        <div>
            <label for="event-location" class="mb-1 block text-xs font-semibold text-slate-600">Location <span class="text-red-500">*</span></label>
            <label class="mb-2 inline-flex items-center gap-2 text-xs font-medium text-gray-600">
                <input type="checkbox" name="virtual" value="1" x-model="f.virtual" class="rounded border-gray-300 text-[#1a8a8e] focus:ring-[#1a8a8e]">
                Virtual / online event
            </label>
            <input id="event-location" name="location" x-model="f.location" required maxlength="255" :placeholder="f.virtual ? 'https://zoom.us/j/…' : 'Building, room'" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="event-host" class="mb-1 block text-xs font-semibold text-slate-600">Host <span class="text-red-500">*</span></label>
                <select id="event-host" name="host" x-model="f.host" required class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
                    <option value="Marian TBI">Marian TBI</option>
                    <template x-for="host in hosts" :key="host">
                        <option :value="host" x-text="host"></option>
                    </template>
                    <option value="__other__">Others</option>
                </select>
            </div>
            <div x-show="f.host==='__other__'" x-cloak>
                <label for="event-host-other" class="mb-1 block text-xs font-semibold text-slate-600">Specify host <span class="text-red-500">*</span></label>
                <input id="event-host-other" name="host_other" x-model="f.hostOther" maxlength="255" :required="f.host==='__other__'" placeholder="Enter host or organizer" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
            <a href="{{ route('events') }}" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="rounded-xl bg-[#b92369] px-4 py-2 text-sm font-semibold text-white hover:bg-[#9a1b55]">{{ $submitLabel }}</button>
        </div>
    </form>
</div>
