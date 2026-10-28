@extends('layouts.app')
@section('title','Ecosystem Contacts')
@section('eyebrow','Network')
@section('heading','Contacts & Stakeholder Directory')
@section('subheading','Faculty, researchers, mentors, investors and partners — with NDA/MOU and verification status.')

@section('banner-actions')
    @if (auth()->user()->canManageRecords())
        <button @click="$dispatch('open-contact-form')" class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-[#1a8a8e] shadow-sm transition hover:bg-[#eaf6f6]">+ Add Contact</button>
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
        <p class="font-semibold">The request could not be completed:</p>
        <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
@if (session('contact_status'))
    <div role="status" class="mb-4 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('contact_status') }}</div>
@endif
<div x-data="{
        rows:@js($contacts), startupOptions:@js($startupOptions), tab:'internal', sel:null,
        assignmentModalOpen:false, assignmentContact:null,
        get list(){ return this.rows.filter(r => r.side===this.tab) },
        get accent(){ return this.tab==='internal' ? '#b92369' : '#1a8a8e' },
        ndaClass(n){ return n==='Pending' ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
        editSelected(){ window.dispatchEvent(new CustomEvent('open-contact-edit',{detail:this.sel})) },
        manageAssignments(){ this.assignmentContact=this.sel; this.assignmentModalOpen=true }
     }"
     x-init="$watch('scope', s => { if(s!=='all') tab=s }); if(scope!=='all') tab=scope">

    {{-- Tabs --}}
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <div class="inline-flex rounded-2xl border border-slate-line bg-white p-1 text-sm font-semibold">
            <button @click="tab='internal'; sel=null" class="rounded-xl px-4 py-2 transition" :class="tab==='internal' ? 'bg-[#b92369] text-white shadow' : 'text-gray-600 hover:bg-gray-50'">Internal Contacts</button>
            <button @click="tab='external'; sel=null" class="rounded-xl px-4 py-2 transition" :class="tab==='external' ? 'bg-[#1a8a8e] text-white shadow' : 'text-gray-600 hover:bg-gray-50'">External Contacts</button>
        </div>
        <p class="text-sm text-gray-500" x-text="tab==='internal' ? 'Faculty Advisors · Campus Researchers · Student Leads · Department Chairs · TTO Officers' : 'Industry Mentors · VCs & Angels · Corporate Partners · Grant Officers · Legal/Compliance'"></p>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- List --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:col-span-2">
            <template x-for="c in list" :key="c.id">
                <button @click="sel=c" class="rounded-2xl border bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                        :class="sel && sel.id===c.id ? (tab==='internal' ? 'border-[#b92369] ring-2 ring-[#b92369]/15' : 'border-[#1a8a8e] ring-2 ring-[#1a8a8e]/15') : 'border-gray-100'">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-full text-sm font-bold text-white" :style="'background:'+accent" x-text="c.name.split(' ').map(w=>w[0]).slice(0,2).join('')"></div>
                        <div class="min-w-0"><p class="truncate font-semibold" x-text="c.name"></p><p class="truncate text-xs text-gray-500" x-text="c.role"></p></div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500" x-text="c.org"></p>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <span class="rounded-lg px-2 py-0.5 text-[11px] font-semibold" :class="tab==='internal' ? 'bg-[#fcf0f5] text-[#b92369]' : 'bg-[#eaf6f6] text-[#1a8a8e]'" x-text="c.verify"></span>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="ndaClass(c.nda)" x-text="c.nda"></span>
                    </div>
                </button>
            </template>
        </div>

        {{-- Detail card --}}
        <aside class="xl:sticky xl:top-24 xl:self-start">
            <div x-show="!sel" class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500">Select a contact to see details.</div>
            <div x-show="sel" x-cloak x-transition class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <template x-if="sel">
                    <div>
                        <div class="px-5 py-5 text-white" :style="'background:'+accent">
                            <p class="text-lg font-bold" x-text="sel.name"></p>
                            <p class="text-sm text-white/80" x-text="sel.role+' · '+sel.org"></p>
                            <span class="mt-3 inline-flex rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-semibold">✓ <span class="ml-1" x-text="sel.verify"></span></span>
                        </div>
                        <div class="space-y-5 p-5 text-sm">
                            <div class="flex flex-wrap gap-2">
                                @if (auth()->user()->canManageRecords())
                                    <button type="button" @click="editSelected()" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit Contact</button>
                                    <button type="button" @click="manageAssignments()" class="rounded-lg border border-[#1a8a8e]/30 bg-[#eaf6f6] px-3 py-2 text-xs font-semibold text-[#146e72] hover:bg-[#d9eeee]">Manage incubatee assignments</button>
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if (auth()->user()?->isSuperAdmin())
                                            <form method="POST" :action="'{{ url('/contacts') }}/'+sel.id+'/send-credentials'" class="inline">
                                                @csrf
                                                <button type="submit" class="rounded-lg border border-[#b92369]/40 px-3 py-2 text-xs font-semibold text-[#b92369] hover:bg-[#fcf0f5]">Send Credentials / Invite Account</button>
                                            </form>
                                        @endif
                                        <button type="button" @click="$dispatch('confirm-action-contact', {title: 'Archive Contact', message: 'Archive '+sel.name+'? You can view or restore it from Archives.', confirmText: 'Yes, Archive', confirmClass: 'bg-red-600 hover:bg-red-700', action: 'archive:'+sel.id})" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100">Archive Contact</button>
                                    </div>
                                @endif
                                @if (auth()->user()->canManageRecords())
                                    <button type="button" x-show="sel.email" @click="$dispatch('open-broadcast',{contactId:sel.id})" class="rounded-lg border border-[#1a8a8e]/30 bg-[#eaf6f6] px-3 py-2 text-xs font-semibold text-[#146e72] hover:bg-[#d9eeee]">Send email</button>
                                @endif
                            </div>
                            <div>
                                <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400">Contact details</p>
                                <a class="block text-sm text-[#1a8a8e] hover:underline" :href="'mailto:' + sel.email" x-text="sel.email"></a>
                                <a x-show="sel.phone" class="mt-1 block text-sm text-[#1a8a8e] hover:underline" :href="'tel:' + sel.phone" x-text="sel.phone"></a>
                                <span x-show="!sel.phone" class="mt-1 block text-xs text-gray-400">No phone number provided</span>
                            </div>
                            <div>
                                <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400">Affiliation</p>
                                <p class="font-medium text-gray-700" x-text="sel.affiliationType"></p>
                                <p class="mt-1 text-gray-500" x-text="sel.org || 'Independent professional'"></p>
                            </div>
                            <div x-show="sel.academicProgram || sel.academicYear">
                                <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400">Academic details</p>
                                <p class="text-gray-700" x-text="[sel.academicProgram, sel.academicYear].filter(Boolean).join(' · ')"></p>
                            </div>
                            <div><p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400">Domain expertise</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="domain in sel.domains" :key="domain"><span class="rounded-lg bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700" x-text="domain"></span></template>
                                    <span x-show="sel.domainOther" class="rounded-lg bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700" x-text="sel.domainOther"></span>
                                </div>
                            </div>
                            <div><p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400">Technical skills</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="skill in sel.technicalSkills" :key="skill"><span class="rounded-lg bg-[#fcf0f5] px-2 py-0.5 text-xs font-medium text-[#8e1c51]" x-text="skill"></span></template>
                                    <span x-show="sel.technicalOther" class="rounded-lg bg-[#fcf0f5] px-2 py-0.5 text-xs font-medium text-[#8e1c51]" x-text="sel.technicalOther"></span>
                                    <span x-show="!sel.technicalSkills.length && !sel.technicalOther" class="text-xs text-gray-400">No specific technical skills listed</span>
                                </div>
                                <p x-show="sel.skillDetails" class="mt-2 text-xs text-gray-500" x-text="sel.skillDetails"></p>
                            </div>
                            <div><p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400">NDA / MOU</p>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1" :class="ndaClass(sel.nda)" x-text="sel.nda"></span></div>
                            <div><p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400">Assigned incubatees</p>
                                <p x-show="!sel.assignments.length" class="text-xs text-gray-500">No confirmed incubatee assignments.</p>
                                <ul class="space-y-3">
                                    <template x-for="assignment in sel.assignments" :key="assignment.startupId">
                                        <li class="rounded-xl border border-gray-100 p-3">
                                            <div class="flex items-start justify-between gap-2">
                                                <div><p class="font-medium text-slate-800" x-text="assignment.startup"></p><p class="text-xs text-gray-500" x-text="'Founder: '+assignment.founder+' · '+assignment.email"></p></div>
                                                <button type="button" @click="$dispatch('confirm-unlink-incubatee', {action: '{{ url('/contacts') }}/'+sel.id+'/assignments/'+assignment.startupId, name: assignment.startupName || 'this incubatee'})" class="text-xs font-semibold text-red-700 hover:underline">Remove</button>
                                            </div>
                                            <p class="mt-2 text-xs text-[#146e72]" x-text="'Confirmed by '+assignment.confirmedBy+' on '+assignment.confirmedAt"></p>
                                            <p class="mt-1 text-xs text-gray-500" x-text="assignment.notes"></p>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                            <div><p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400">Activity log</p>
                                <ul class="space-y-2 border-l-2 border-gray-100 pl-3"><template x-for="l in sel.log"><li class="text-xs text-gray-600" x-text="l"></li></template></ul></div>
                        </div>

                        <div x-show="assignmentModalOpen" x-cloak x-transition.opacity @click.self="assignmentModalOpen=false" @keydown.escape.window="assignmentModalOpen=false"
                             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
                            <section role="dialog" aria-modal="true" aria-labelledby="assignment-modal-title" class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                                <header class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-5">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wider text-[#1a8a8e]">Admin-confirmed relationship</p>
                                        <h2 id="assignment-modal-title" class="mt-1 text-lg font-bold">Assign incubatee to contact</h2>
                                        <p class="mt-1 text-sm text-gray-500" x-text="assignmentContact ? assignmentContact.name : ''"></p>
                                    </div>
                                    <button type="button" @click="assignmentModalOpen=false" aria-label="Close assignment form" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100">✕</button>
                                </header>
                                <form method="POST" :action="assignmentContact ? '{{ url('/contacts') }}/'+assignmentContact.id+'/assignments' : '#'">
                                    @csrf
                                    <div class="space-y-4 px-6 py-5">
                                        <div>
                                            <label for="assignment-startup" class="mb-1 block text-xs font-semibold text-slate-600">Existing incubatee</label>
                                            <select id="assignment-startup" name="startup_id" required class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
                                                <option value="">Select an incubatee</option>
                                                <template x-for="startup in startupOptions" :key="startup.id">
                                                    <option :value="startup.id" x-text="startup.name+' · Founder: '+startup.founder"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="assignment-verification-method" class="mb-1 block text-xs font-semibold text-slate-600">How was the relationship verified?</label>
                                            <select id="assignment-verification-method" name="verification_method" required class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
                                                <option value="">Choose verification method</option>
                                                <option value="founder_email_match">Contact email matches the incubatee's registered founder/contact email</option>
                                                <option value="program_records">Incubation program records checked</option>
                                                <option value="authorized_confirmation">Confirmed with an authorized founder or organization representative</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="assignment-verification-notes" class="mb-1 block text-xs font-semibold text-slate-600">Verification evidence / notes</label>
                                            <textarea id="assignment-verification-notes" name="verification_notes" required maxlength="1000" rows="4" placeholder="Record what was checked, such as the matching founder email or the program record reference." class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm"></textarea>
                                        </div>
                                        <p class="rounded-xl bg-blue-50 px-3 py-2 text-xs leading-5 text-blue-900">Selecting an incubatee creates a confirmed link only after the reviewer records how the contact's relationship was checked. A founder is not automatically linked just because they registered.</p>
                                    </div>
                                    <footer class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4">
                                        <button type="button" @click="assignmentModalOpen=false" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Cancel</button>
                                        <button type="submit" class="rounded-xl bg-[#1a8a8e] px-4 py-2 text-sm font-semibold text-white hover:bg-[#146e72]">Confirm assignment</button>
                                    </footer>
                                </form>
                            </section>
                        </div>
                    </div>
                </template>
            </div>
        </aside>
    </div>
    <div x-data="{confirmOpen:false, confirmData:{title:'', message:'', confirmText:'Confirm', confirmClass:'bg-red-600 hover:bg-red-700', action:''}, unlinkOpen:false, unlink:{action:'', name:''}}" @confirm-action-contact.window="confirmData = $event.detail; confirmOpen = true" @confirm-unlink-incubatee.window="unlink = $event.detail; unlinkOpen = true" x-show="confirmOpen || unlinkOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm">
        <div x-show="confirmOpen" class="w-full max-w-md rounded-xl bg-white shadow-2xl">
            <div class="px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900" x-text="confirmData.title"></h3>
                <p class="mt-2 text-sm text-gray-600" x-text="confirmData.message"></p>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4">
                <button type="button" @click="confirmOpen=false" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="button" @click="
                    const action = confirmData.action || '';
                    if (action.startsWith('archive:')) {
                        const id = action.split(':')[1];
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '{{ url('/contacts') }}/' + id;
                        const csrf = document.createElement('input');
                        csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '{{ csrf_token() }}';
                        const method = document.createElement('input');
                        method.type = 'hidden'; method.name = '_method'; method.value = 'DELETE';
                        form.appendChild(csrf);
                        form.appendChild(method);
                        document.body.appendChild(form);
                        form.submit();
                    }
                    confirmOpen=false;
                " class="px-4 py-2 text-white rounded-lg" :class="confirmData.confirmClass" x-text="confirmData.confirmText"></button>
            </div>
        </div>
        <div x-show="unlinkOpen" class="w-full max-w-lg bg-white rounded-xl shadow-2xl p-6 border border-gray-100">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Remove Incubatee Link</h3>
            <p class="text-sm text-gray-600 mb-6">Are you sure you want to remove this incubatee from this contact? This will update their directory mapping.</p>
            <form :action="unlink.action" method="POST">
                @csrf
                @method('DELETE')
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <button type="button" @click="unlinkOpen=false" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 text-sm font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-lg">Yes, Unlink Incubatee</button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('partials.broadcast-modal')
@endsection
