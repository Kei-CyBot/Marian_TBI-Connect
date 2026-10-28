@extends('layouts.app')
@section('title','Incubatees & Startups')
@section('eyebrow','Directory')
@section('heading','Incubatees & Startups')
@section('subheading','Manage startup cohorts and the Internal Academic and External Industry tracks.')

@section('content')
@if (session('startup_status'))
    <div role="status" class="mb-4 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('startup_status') }}</div>
@endif
@if (session('assessment_status'))
    <div role="status" class="mb-4 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('assessment_status') }}</div>
@endif
@if (session('drive_link_status'))
    <div role="status" class="mb-4 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('drive_link_status') }}</div>
@endif
@if ($errors->any())
    <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <p class="font-semibold">The request could not be completed:</p>
        <ul class="mt-1 list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<div x-data="{
        rows:@js($startups),
        assessments:@js($assessments),
        driveLinks:@js($driveLinks),
        cohorts:[{id:1,name:'Cohort 1'},{id:2,name:'Cohort 2'},{id:3,name:'Cohort 3'}],
        nextCohortNumber:4,
        view:'grid', q:'', cohortFilter:'', trackFilter:'', statusFilter:'', colMenu:false,
        cols:{track:true,cohort:true,status:true,founder:true,ventureType:true,metadata:true},
        ventureTypes:['Faculty Spin-off','Student Venture','Research Lab','Commercial Startup','Corporate Spin-out','Regional Business'],
        entityTypes:['Inc.','LLC','Corp','Partnership','Sole Prop'],
        statuses:['Applicant','Incubatee','Incubatee Extended','Graduate','Alumni Network'],
        statusStyle:{'Applicant':'bg-amber-50 text-amber-700 ring-amber-200','Incubatee':'bg-sky-50 text-sky-700 ring-sky-200','Incubatee Extended':'bg-indigo-50 text-indigo-700 ring-indigo-200','Graduate':'bg-emerald-50 text-emerald-700 ring-emerald-200','Alumni Network':'bg-gray-100 text-gray-700 ring-gray-200'},
        formModalOpen:false, formMode:'create', formError:'', draft:{},
        cohortModalOpen:false, cohortMode:'add', cohortDraft:{id:null,name:''}, cohortError:'',
        driveModalOpen:false, driveStartup:null,
        get colLabels(){ return {track:'Track',cohort:'Cohort',status:'Status',founder:'Founder',ventureType:'Venture type',metadata:this.trackFilter==='internal'?'Campus Department':this.trackFilter==='external'?'Legal Entity Type':'Track Metadata'} },
        get list(){
            return this.rows.filter(r =>
                (!this.trackFilter || r.track===this.trackFilter) &&
                (!this.cohortFilter || r.cohortId===Number(this.cohortFilter)) &&
                (!this.statusFilter || r.status===this.statusFilter) &&
                (!this.q || (r.name+' '+r.founder+' '+(r.ventureType||'')).toLowerCase().includes(this.q.toLowerCase())))
        },
        cohortName(id){ return this.cohorts.find(c=>c.id===id)?.name || 'Unassigned' },
        openCreate(){
            this.formMode='create';
            this.formError='';
            this.draft={id:null,name:'',founder:'',email:'',track:this.trackFilter || 'internal',ventureType:'',ventureTypeOther:'',cohortId:this.cohorts[0]?.id || '',campusDepartment:'',legalEntityType:'',legalEntityTypeOther:'',status:'Applicant'};
            this.formModalOpen=true;
        },
        openEdit(row){
            this.formMode='edit';
            this.formError='';
            this.draft={...row};
            if(this.draft.ventureType && !this.ventureTypes.includes(this.draft.ventureType)){
                this.draft.ventureTypeOther=this.draft.ventureType;
                this.draft.ventureType='__other__';
            }
            if(this.draft.legalEntityType && !this.entityTypes.includes(this.draft.legalEntityType)){
                this.draft.legalEntityTypeOther=this.draft.legalEntityType;
                this.draft.legalEntityType='__other__';
            }
            this.formModalOpen=true;
        },
        changeTrack(track){
            this.draft.track=track;
            this.draft.campusDepartment='';
            this.draft.legalEntityType='';
            this.draft.legalEntityTypeOther='';
        },
        saveStartup(){
            this.formError='';
            if(!this.draft.name.trim() || !this.draft.founder.trim() || !this.draft.email.trim()){
                this.formError='Enter the startup name, lead founder, and contact email.';
                return;
            }
            if(!this.draft.cohortId){
                this.formError='Assign the startup to a cohort.';
                return;
            }
            if(this.draft.ventureType==='__other__' && !this.draft.ventureTypeOther.trim()){
                this.formError='Enter the custom venture type.';
                return;
            }
            if(this.draft.track==='external' && this.draft.legalEntityType==='__other__' && !this.draft.legalEntityTypeOther.trim()){
                this.formError='Enter the custom legal entity type.';
                return;
            }
            this.$refs.startupForm.submit();
        },
        openCohort(mode){
            this.cohortError='';
            this.cohortMode=mode;
            const selected=this.cohorts.find(c=>c.id===Number(this.cohortFilter));
            this.cohortDraft=mode==='add' ? {id:null,name:'Cohort '+this.nextCohortNumber} : {id:selected?.id || null,name:selected?.name || ''};
            this.cohortModalOpen=true;
        },
        saveCohort(){
            this.cohortError='';
            const name=this.cohortDraft.name.trim();
            if(!name){ this.cohortError='Enter a cohort name.'; return }
            if(this.cohorts.some(c=>c.id!==this.cohortDraft.id && c.name.toLowerCase()===name.toLowerCase())){
                this.cohortError='A cohort with that name already exists.';
                return;
            }
            if(this.cohortMode==='add'){
                const cohort={id:this.nextCohortNumber,name};
                this.cohorts.push(cohort);
                this.cohortFilter=String(cohort.id);
                this.nextCohortNumber++;
            } else {
                const cohort=this.cohorts.find(c=>c.id===this.cohortDraft.id);
                if(cohort) cohort.name=name;
            }
            this.cohortModalOpen=false;
        },
        deleteCohort(){
            this.cohortError='';
            if(this.cohorts.length<=1){ this.cohortError='At least one cohort must remain.'; return }
            const cohort=this.cohorts.find(c=>c.id===this.cohortDraft.id);
            if(!cohort) return;
            const replacement=this.cohorts.find(c=>c.id!==cohort.id);
            this.rows.forEach(r=>{ if(r.cohortId===cohort.id) r.cohortId=replacement.id });
            this.cohorts=this.cohorts.filter(c=>c.id!==cohort.id);
            if(Number(this.cohortFilter)===cohort.id) this.cohortFilter='';
            this.cohortModalOpen=false;
        },
        startForm(row){
            this.$dispatch('open-source-startup-forms',{startup:row.name,track:row.track});
        },
        startAssessment(row){
            this.$dispatch('open-startup-assessment',{id:row.id,name:row.name});
        },
        assessmentFor(row){
            return this.assessments[String(row.id)] || null;
        },
        linksFor(row){
            return this.driveLinks[String(row.id)] || [];
        },
        openDriveLinks(row){
            this.driveStartup=row;
            this.driveModalOpen=true;
        },
        closeModals(){
            this.formModalOpen=false;
            this.cohortModalOpen=false;
            this.driveModalOpen=false;
        },
        openFromHeader(){
            if(new URLSearchParams(window.location.search).get('openOnboarding')==='1'){
                window.history.replaceState(null, '', window.location.pathname);
                this.openCreate();
            }
        }
     }"
     x-init="openFromHeader()"
     @open-incubatee-onboarding.window="openCreate()"
     @keydown.escape.window="closeModals()">

    <div class="mb-5 space-y-3">
        <div class="flex flex-wrap items-center gap-3">
            <input x-model="q" type="search" placeholder="Search startups or founders…" class="w-full max-w-xs rounded-xl border border-slate-line bg-white px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
            <select x-model="trackFilter" aria-label="Filter by incubatee track" class="rounded-xl border border-slate-line bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                <option value="">All tracks</option>
                <option value="internal">Internal Academic</option>
                <option value="external">External Industry</option>
            </select>
            <select x-model="cohortFilter" aria-label="Filter and select cohort" class="rounded-xl border border-slate-line bg-white px-3 py-2 text-sm">
                <option value="">All cohorts</option>
                <template x-for="cohort in cohorts" :key="cohort.id"><option :value="cohort.id" x-text="cohort.name"></option></template>
            </select>
            <select x-model="statusFilter" aria-label="Filter by status" class="rounded-xl border border-slate-line bg-white px-3 py-2 text-sm">
                <option value="">All statuses</option>
                <template x-for="item in statuses" :key="item"><option :value="item" x-text="item"></option></template>
            </select>
            <span class="text-sm text-gray-500"><span x-text="list.length"></span> startups</span>
        </div>

        <div class="cohort-toolbar flex flex-wrap items-center justify-start gap-2">
            @if (auth()->user()->canManageRecords())
                <button type="button" @click="openCohort('add')" class="cohort-add rounded-xl border bg-[#b92369] px-3.5 py-2 text-sm font-semibold text-white shadow-sm">Add Cohort</button>
                <button type="button" @click="openCohort('rename')" :disabled="!cohortFilter" class="cohort-manage rounded-xl border border-[#1a8a8e]/30 bg-[#eaf6f6] px-3 py-2 text-sm font-semibold text-[#1a8a8e] disabled:cursor-not-allowed">Edit / Rename</button>
                <button type="button" @click="openCohort('delete')" :disabled="!cohortFilter || cohorts.length<=1" class="cohort-delete rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 disabled:cursor-not-allowed">Delete</button>
            @endif
            <div class="inline-flex rounded-xl border border-slate-line bg-white p-0.5 text-sm font-medium">
                <button type="button" @click="view='grid'" class="rounded-lg px-3 py-1.5" :class="view==='grid' ? 'bg-[#b92369] text-white' : 'text-gray-600'">Grid</button>
                <button type="button" @click="view='table'" class="rounded-lg px-3 py-1.5" :class="view==='table' ? 'bg-[#b92369] text-white' : 'text-gray-600'">Table</button>
            </div>
            @if (auth()->user()->canManageRecords())
                <button type="button" @click="openCreate()" class="rounded-xl bg-[#b92369] px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#9a1b55]">+ Add Incubatee</button>
            @endif
        </div>
    </div>

    <div x-show="view==='grid'" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <template x-for="row in list" :key="row.id">
            <article class="flex h-full flex-col rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-sm font-bold" :class="row.track==='internal' ? 'bg-[#fcf0f5] text-[#b92369]' : 'bg-[#eaf6f6] text-[#1a8a8e]'" x-text="row.name.slice(0,2).toUpperCase()"></div>
                        <div class="min-w-0"><h3 class="truncate font-semibold leading-tight" x-text="row.name"></h3><p class="truncate text-xs text-gray-500" x-text="row.founder"></p></div>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="statusStyle[row.status]" x-text="row.status"></span>
                </div>
                <div class="mt-4 flex flex-wrap gap-1.5">
                    <span class="rounded-lg px-2 py-0.5 text-xs font-semibold" :class="row.track==='internal' ? 'bg-[#fcf0f5] text-[#b92369]' : 'bg-[#eaf6f6] text-[#1a8a8e]'" x-text="row.track==='internal' ? 'Internal Academic' : 'External Industry'"></span>
                    <span class="rounded-lg bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600" x-text="cohortName(row.cohortId)"></span>
                    <span x-show="row.ventureType" class="rounded-lg border border-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600" x-text="row.ventureType"></span>
                </div>
                <dl class="mt-4 border-t border-gray-100 pt-4 text-xs">
                    <template x-if="row.track==='internal' && row.campusDepartment">
                        <div><dt class="text-gray-400">Campus Department</dt><dd class="font-medium" x-text="row.campusDepartment"></dd></div>
                    </template>
                    <template x-if="row.track==='external' && row.legalEntityType">
                        <div><dt class="text-gray-400">Legal Entity Type</dt><dd class="font-medium" x-text="row.legalEntityType"></dd></div>
                    </template>
                </dl>
                <div x-show="assessmentFor(row)" class="mt-3 text-xs text-gray-500">
                    Assessment score:
                    <span class="font-semibold text-[#b92369]" x-text="assessmentFor(row)?.totalPoints+' / '+(Object.keys(assessmentFor(row)?.ratings || {}).length*5)"></span>
                </div>
                <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3 text-xs">
                    <span class="text-gray-500">Google Drive files</span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 font-semibold text-gray-600" x-text="linksFor(row).length+' link'+(linksFor(row).length===1?'':'s')"></span>
                </div>
                <a :href="'{{ route('technical-needs') }}?startup_id='+row.id" class="mt-3 flex items-center justify-between rounded-xl border border-teal-200 bg-teal-50/70 px-3 py-2 text-xs font-semibold text-teal-900 transition hover:border-teal-300 hover:bg-teal-100">
                    <span>Technical needs folder</span>
                    <span aria-hidden="true">Open &rarr;</span>
                </a>
                @if (auth()->user()->canManageRecords())
                <div class="mt-auto grid grid-cols-2 gap-2 pt-4">
                    <button type="button" @click="startForm(row)" class="inline-flex h-10 min-w-0 items-center justify-center whitespace-nowrap rounded-xl px-2 text-xs font-semibold text-white transition focus:outline-none focus:ring-2 focus:ring-offset-2" :class="row.track==='internal' ? 'bg-[#b92369] hover:bg-[#9a1b55] focus:ring-[#b92369]' : 'bg-[#1a8a8e] hover:bg-[#146e72] focus:ring-[#1a8a8e]'">Fill forms</button>
                    <button type="button" @click="startAssessment(row)" class="inline-flex h-10 min-w-0 items-center justify-center whitespace-nowrap rounded-xl border border-[#b92369]/25 bg-[#fcf0f5]/60 px-2 text-xs font-semibold text-[#b92369] transition hover:bg-[#fcf0f5] focus:outline-none focus:ring-2 focus:ring-[#b92369]/20 focus:ring-offset-2">Assessment</button>
                    <button type="button" @click="openEdit(row)" class="inline-flex h-10 min-w-0 items-center justify-center whitespace-nowrap rounded-xl border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2">Edit</button>
                    <button type="button" @click="openDriveLinks(row)" class="inline-flex h-10 min-w-0 items-center justify-center whitespace-nowrap rounded-xl border border-[#1a8a8e]/25 bg-[#eaf6f6] px-2 text-xs font-semibold text-[#146e72] transition hover:bg-[#d9eeee] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/20 focus:ring-offset-2">Drive links</button>
                    <button type="button" @click="$dispatch('confirm-archive-incubatee', {action: '{{ url('/incubatees') }}/'+row.id, name: row.name})" class="col-span-2 w-full rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">Archive</button>
                </div>
                @endif
            </article>
        </template>
    </div>

    <div x-data="{archiveOpen:false, archive:{action:'', name:''}}" @confirm-archive-incubatee.window="archive = $event.detail; archiveOpen = true" x-show="archiveOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="relative w-full max-w-lg bg-white rounded-xl shadow-2xl p-6 border border-gray-100">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Archive Incubatee Record</h3>
            <p class="text-sm text-gray-600 mb-6">Are you sure you want to archive this incubatee record?</p>
            <form :action="archive.action" method="POST">
                @csrf
                @method('DELETE')
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <button type="button" @click="archiveOpen=false" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 text-sm font-bold text-white bg-red-600 hover:bg-red-700 rounded-lg">Yes, Archive Incubatee</button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="view==='table'" x-cloak class="overflow-x-auto rounded-2xl border border-gray-100 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">Startup</th>
                    <template x-for="(label,key) in colLabels" :key="key"><th class="px-4 py-3" x-show="cols[key]" x-text="label"></th></template>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <template x-for="row in list" :key="row.id">
                    <tr class="hover:bg-gray-50/70">
                        <td class="px-4 py-3"><p class="font-semibold" x-text="row.name"></p><p class="text-xs text-gray-400" x-text="row.email"></p></td>
                        <td class="px-4 py-3" x-show="cols.track"><span class="rounded-lg px-2 py-0.5 text-xs font-semibold" :class="row.track==='internal' ? 'bg-[#fcf0f5] text-[#b92369]' : 'bg-[#eaf6f6] text-[#1a8a8e]'" x-text="row.track==='internal' ? 'Internal Academic' : 'External Industry'"></span></td>
                        <td class="px-4 py-3" x-show="cols.cohort" x-text="cohortName(row.cohortId)"></td>
                        <td class="px-4 py-3" x-show="cols.status"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="statusStyle[row.status]" x-text="row.status"></span></td>
                        <td class="px-4 py-3" x-show="cols.founder" x-text="row.founder"></td>
                        <td class="px-4 py-3" x-show="cols.ventureType" x-text="row.ventureType || '—'"></td>
                        <td class="px-4 py-3" x-show="cols.metadata" x-text="row.track==='internal' ? (row.campusDepartment || '—') : (row.legalEntityType || '—')"></td>
                        <td class="px-4 py-3">
                            @if (auth()->user()->canManageRecords())
                            <div class="flex min-w-max items-center gap-2">
                                <button type="button" @click="startForm(row)" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold" :class="row.track==='internal' ? 'bg-[#fcf0f5] text-[#b92369] hover:bg-[#f8e2ec]' : 'bg-[#eaf6f6] text-[#1a8a8e] hover:bg-[#d9eeee]'">Fill forms</button>
                                <button type="button" @click="startAssessment(row)" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-[#b92369] hover:bg-[#fcf0f5]">Assessment</button>
                                <button type="button" @click="openEdit(row)" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">Edit</button>
                                <button type="button" @click="openDriveLinks(row)" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-[#146e72] hover:bg-[#eaf6f6]">Drive links (<span x-text="linksFor(row).length"></span>)</button>
                                <form method="POST" :action="'{{ url('/incubatees') }}/'+row.id" @submit="if (!confirm('Archive '+row.name+'? Its related records will be retained.')) $event.preventDefault()">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-50">Archive</button>
                                </form>
                            </div>
                            @endif
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div x-show="!list.length" x-cloak class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-white py-16 text-center text-sm text-gray-500">No incubatees match the current track, cohort, and status filters.</div>

    {{-- Google Drive links for one incubatee --}}
    <div x-show="driveModalOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="driveModalOpen=false">
        <section role="dialog" aria-modal="true" aria-labelledby="drive-links-title" class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <header class="flex items-start justify-between gap-4 bg-[#1a8a8e] px-6 py-5 text-white">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-white/70">Incubatee file links</p>
                    <h2 id="drive-links-title" class="mt-1 text-lg font-bold" x-text="driveStartup?.name"></h2>
                </div>
                <button type="button" @click="driveModalOpen=false" aria-label="Close Google Drive links" class="rounded-lg p-1.5 text-white/80 hover:bg-white/15">✕</button>
            </header>
            <div class="space-y-5 p-6">
                <p class="text-sm leading-6 text-gray-600">Paste a Google Drive folder or Google Docs link for this incubatee. Anyone who opens it must also have permission in Google Drive.</p>

                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Saved links</h3>
                    <p x-show="!linksFor(driveStartup || {}).length" class="rounded-xl border border-dashed border-gray-200 px-4 py-5 text-center text-sm text-gray-500">No Drive links have been added for this incubatee.</p>
                    <ul x-show="linksFor(driveStartup || {}).length" class="space-y-2">
                        <template x-for="link in linksFor(driveStartup || {})" :key="link.id">
                            <li class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 px-3 py-2.5">
                                <a :href="link.url" target="_blank" rel="noopener noreferrer" class="min-w-0 truncate text-sm font-semibold text-[#146e72] hover:underline" x-text="link.label"></a>
                                @if (auth()->user()->canManageRecords())
                                    <form method="POST" :action="'{{ url('/startup-drive-links') }}/'+link.id" @submit="if (!confirm('Remove this Drive link?')) $event.preventDefault()">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="shrink-0 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Remove</button>
                                    </form>
                                @endif
                            </li>
                        </template>
                    </ul>
                </div>

                @if (auth()->user()->canManageRecords())
                <form method="POST" action="{{ route('startup-drive-links.store') }}" class="space-y-4 border-t border-gray-100 pt-5">
                    @csrf
                    <input type="hidden" name="startup_id" :value="driveStartup?.id">
                    <div>
                        <label for="drive-link-label" class="mb-1 block text-xs font-semibold text-slate-600">Link name</label>
                        <input id="drive-link-label" name="label" required maxlength="120" placeholder="e.g. Shared project folder" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                    </div>
                    <div>
                        <label for="drive-link-url" class="mb-1 block text-xs font-semibold text-slate-600">Google Drive or Docs URL</label>
                        <input id="drive-link-url" name="url" type="url" required maxlength="2048" placeholder="https://drive.google.com/..." class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                    </div>
                    <p class="text-xs text-gray-500">Only links hosted on drive.google.com or docs.google.com are accepted. Link sharing permissions are managed in Google Drive.</p>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="driveModalOpen=false" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Close</button>
                        <button type="submit" class="rounded-xl bg-[#1a8a8e] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#146e72]">Add Drive Link</button>
                    </div>
                </form>
                @endif
            </div>
        </section>
    </div>

    {{-- Create and edit incubatee modal --}}
    <div x-show="formModalOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="formModalOpen=false">
        <section role="dialog" aria-modal="true" aria-labelledby="incubatee-modal-title" class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <form x-ref="startupForm" method="POST" :action="formMode==='edit' ? '{{ url('/incubatees') }}/'+draft.id : '{{ route('incubatees.store') }}'" @submit.prevent="saveStartup()">
                @csrf
                <input type="hidden" name="_method" value="PUT" :disabled="formMode!=='edit'">
                <header class="grad flex items-start justify-between gap-4 rounded-t-2xl px-6 py-5 text-white">
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-white/70" x-text="formMode==='create' ? 'Startup onboarding' : 'Startup profile'"></p><h2 id="incubatee-modal-title" class="mt-1 text-lg font-bold" x-text="formMode==='create' ? 'Add Incubatee' : 'Edit Incubatee'"></h2></div>
                    <button type="button" @click="formModalOpen=false" aria-label="Close modal" class="rounded-lg p-1.5 text-white/80 hover:bg-white/15">✕</button>
                </header>
                <div class="space-y-4 p-6">
                    <p x-show="formError" x-cloak role="alert" class="rounded-xl bg-red-50 px-3 py-2 text-sm font-medium text-red-700" x-text="formError"></p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2"><label for="startup-name" class="mb-1 block text-xs font-semibold text-slate-600">Startup name <span class="text-red-500">*</span></label><input id="startup-name" name="name" x-model="draft.name" required class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15"></div>
                        <div><label for="startup-founder" class="mb-1 block text-xs font-semibold text-slate-600">Lead founder <span class="text-red-500">*</span></label><input id="startup-founder" name="founder" x-model="draft.founder" required class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15"></div>
                        <div><label for="startup-email" class="mb-1 block text-xs font-semibold text-slate-600">Contact email <span class="text-red-500">*</span></label><input id="startup-email" name="email" type="email" x-model="draft.email" required class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15"></div>
                        <div><label for="startup-track" class="mb-1 block text-xs font-semibold text-slate-600">Track</label>
                            <select id="startup-track" name="track" x-model="draft.track" @change="changeTrack($event.target.value)" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm"><option value="internal">Internal Academic</option><option value="external">External Industry</option></select>
                        </div>
                        <div><label for="startup-cohort" class="mb-1 block text-xs font-semibold text-slate-600">Cohort</label>
                            <select id="startup-cohort" name="cohort_id" x-model="draft.cohortId" required class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm"><template x-for="cohort in cohorts" :key="cohort.id"><option :value="cohort.id" x-text="cohort.name"></option></template></select>
                        </div>
                        <div><label for="startup-status" class="mb-1 block text-xs font-semibold text-slate-600">Pipeline Status</label>
                            <select id="startup-status" name="status" x-model="draft.status" required class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm"><template x-for="item in statuses" :key="item"><option :value="item" x-text="item"></option></template></select>
                        </div>
                        <div><label for="venture-type" class="mb-1 block text-xs font-semibold text-slate-600">Venture Type <span class="font-normal text-gray-400">(optional)</span></label>
                            <select id="venture-type" name="venture_type" x-model="draft.ventureType" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm"><option value="">Select venture type</option><template x-for="type in ventureTypes" :key="type"><option :value="type" x-text="type"></option></template><option value="__other__">Other</option></select>
                        </div>
                        <div x-show="draft.ventureType==='__other__'" x-cloak><label for="venture-type-other" class="mb-1 block text-xs font-semibold text-slate-600">Specify venture type <span class="text-red-500">*</span></label><input id="venture-type-other" name="venture_type_other" x-model="draft.ventureTypeOther" maxlength="255" :required="draft.ventureType==='__other__'" placeholder="Enter venture type" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm"></div>
                        <div x-show="draft.track==='internal'" x-cloak><label for="campus-department" class="mb-1 block text-xs font-semibold text-slate-600">Campus Department <span class="font-normal text-gray-400">(optional)</span></label><input id="campus-department" name="campus_department" x-model="draft.campusDepartment" placeholder="e.g. Dept. of Agronomy" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm"></div>
                        <div x-show="draft.track==='external'" x-cloak><label for="legal-entity-type" class="mb-1 block text-xs font-semibold text-slate-600">Legal Entity Type <span class="font-normal text-gray-400">(optional)</span></label>
                            <select id="legal-entity-type" name="legal_entity_type" x-model="draft.legalEntityType" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm"><option value="">Select legal entity type</option><template x-for="type in entityTypes" :key="type"><option :value="type" x-text="type"></option></template><option value="__other__">Other</option></select>
                        </div>
                        <div x-show="draft.track==='external' && draft.legalEntityType==='__other__'" x-cloak><label for="legal-entity-type-other" class="mb-1 block text-xs font-semibold text-slate-600">Specify legal entity type <span class="text-red-500">*</span></label><input id="legal-entity-type-other" name="legal_entity_type_other" x-model="draft.legalEntityTypeOther" maxlength="255" :required="draft.legalEntityType==='__other__'" placeholder="Enter legal entity type" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm"></div>
                    </div>
                    <footer class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button type="button" @click="formModalOpen=false" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="rounded-xl bg-[#b92369] px-4 py-2 text-sm font-semibold text-white hover:bg-[#9a1b55]" x-text="formMode==='create' ? 'Create Incubatee' : 'Save Changes'"></button>
                    </footer>
                </div>
            </form>
        </section>
    </div>

    {{-- Cohort management modal --}}
    <div x-show="cohortModalOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="cohortModalOpen=false">
        <section role="dialog" aria-modal="true" aria-labelledby="cohort-modal-title" class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4">
                <div><p class="text-xs font-semibold uppercase tracking-wider text-[#b92369]" x-text="cohortMode==='add' ? 'Cohort Management' : cohortMode==='rename' ? 'Edit Cohort' : 'Remove Cohort'"></p><h2 id="cohort-modal-title" class="mt-1 text-lg font-bold" x-text="cohortMode==='add' ? 'Add Cohort' : cohortMode==='rename' ? 'Rename Cohort' : 'Delete Cohort'"></h2></div>
                <button type="button" @click="cohortModalOpen=false" aria-label="Close modal" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100">✕</button>
            </header>
            <div class="space-y-4 p-5">
                <p x-show="cohortError" x-cloak role="alert" class="rounded-xl bg-red-50 px-3 py-2 text-sm font-medium text-red-700" x-text="cohortError"></p>
                <template x-if="cohortMode!=='delete'">
                    <div><label for="cohort-name" class="mb-1 block text-xs font-semibold text-slate-600">Cohort name</label><input id="cohort-name" x-model="cohortDraft.name" @keydown.enter.prevent="saveCohort()" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15"></div>
                </template>
                <p x-show="cohortMode==='delete'" class="text-sm leading-6 text-gray-600">Delete <strong x-text="cohortDraft.name"></strong>? Its incubatees will be reassigned to another cohort. At least one cohort must remain.</p>
                <footer class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <button type="button" @click="cohortModalOpen=false" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button x-show="cohortMode!=='delete'" type="button" @click="saveCohort()" class="rounded-xl bg-[#b92369] px-4 py-2 text-sm font-semibold text-white hover:bg-[#9a1b55]" x-text="cohortMode==='add' ? 'Add Cohort' : 'Save Name'"></button>
                    <button x-show="cohortMode==='delete'" type="button" @click="deleteCohort()" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Delete Cohort</button>
                </footer>
            </div>
        </section>
    </div>

</div>
<div id="startup-forms-root" data-submit-url="{{ route('form-submissions.store') }}"></div>
@include('partials.startup-assessment')
@include('partials.form-filler')
@endsection
