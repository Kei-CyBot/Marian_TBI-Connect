{{-- Open with: $dispatch('open-form-filler') or $dispatch('open-form-filler', {id: 9}) --}}
<div x-data="{
        open:false, step:1, error:'', toast:'',
        formId:2, track:'internal', startup:'',
        signerName:'', prerequisiteSelected:false,
        steps:['Form & track','Form requirements','Review & sign'],
        f:{ttoFiled:'',ttoUniv:'',ttoCo:'',ttoRef:'',advisor:'',chairOk:false,
           secId:'',taxNo:''},
        equipment:[
            {n:'3D printer',on:false},{n:'PCR / thermal cycler',on:false},{n:'Soil & plant testing kit',on:false},
            {n:'High-performance workstation',on:false},{n:'Electronics bench',on:false}
        ],
        owners:[{name:'',type:'Founder',pct:60},{name:'',type:'Investor',pct:40}],
        sig:{founder:null,reviewer:null,director:null},
        get catalog(){ return window.FORM_CATALOG || [] },
        get form(){ return this.catalog.find(x=>x.id===this.formId) || {title:'',tracks:'both',category:'',attachmentOnly:false,prerequisite:null} },
        get academicOnly(){ return this.form.tracks==='internal' },
        get accent(){ return this.track==='internal' ? '#b92369' : '#1a8a8e' },
        get tint(){ return this.track==='internal' ? '#fcf0f5' : '#eaf6f6' },
        get ownerTotal(){ return this.owners.reduce((t,o)=>t+(Number(o.pct)||0),0) },
        get submissionData(){
            return JSON.stringify({
                ...this.f,
                equipment:this.equipment.filter(item=>item.on).map(item=>item.n),
                owners:this.owners
            });
        },
        get flow(){
            return [
                {key:'founder',  label:'Submitted by Founder',                         role:'Founder'},
                {key:'reviewer', label:this.track==='internal' ? 'Reviewed by TTO' : 'Reviewed by Mentor', role:this.track==='internal' ? 'TTO Officer' : 'Industry Mentor'},
                {key:'director', label:'Final Approval by Incubation Director',        role:'Incubation Director'}
            ];
        },
        stateOf(i){
            const k=this.flow[i].key;
            if(this.sig[k]) return 'done';
            return (i===0 || this.sig[this.flow[i-1].key]) ? 'current' : 'waiting';
        },

        start(d={}){
            this.step=1; this.error=''; this.sig={founder:null,reviewer:null,director:null};
            this.formId=2; this.track='internal'; this.startup=''; this.signerName=''; this.prerequisiteSelected=false;
            this.f={ttoFiled:'',ttoUniv:'',ttoCo:'',ttoRef:'',advisor:'',chairOk:false,secId:'',taxNo:''};
            this.equipment.forEach(item=>item.on=false);
            this.owners=[{name:'',type:'Founder',pct:60},{name:'',type:'Investor',pct:40}];
            if(d && d.id){ this.formId=d.id }
            if(d && d.startup){ this.startup=d.startup }
            if(d && d.track){ this.track=d.track }
            if(this.academicOnly){ this.track='internal' }
            this.$nextTick(()=>{
                const upload=this.$refs.submissionForm?.querySelector('[name=prerequisite]');
                if(upload) upload.value='';
            });
            this.open=true;
        },
        close(){ this.open=false; this.error=''; },
        pickForm(){
            if(this.academicOnly) this.track='internal';
            this.prerequisiteSelected=false;
            const upload=this.$refs.submissionForm?.querySelector('[name=prerequisite]');
            if(upload) upload.value='';
        },
        setTrack(t){ if(this.academicOnly && t==='external') return; this.track=t },
        validate(){
            this.error='';
            if(this.step===1 && !this.startup.trim()){ this.error='Enter the startup this form is for.'; return false }
            if(this.step===2 && this.track==='external' && this.ownerTotal!==100){ this.error='Ownership shares must add up to 100% (currently '+this.ownerTotal+'%).'; return false }
            if(this.step===2 && this.track==='internal' && !this.f.ttoFiled){ this.error='Answer whether a TTO disclosure has been filed.'; return false }
            if(this.step===2 && this.form.attachmentOnly && !this.prerequisiteSelected){ this.error='Attach the required PDF before continuing.'; return false }
            return true;
        },
        next(){ if(this.validate()) this.step++ },
        back(){ this.error=''; this.step-- },
        addOwner(){ this.owners.push({name:'',type:'Founder',pct:0}) },
        signAndSubmit(){
            if(!this.signerName.trim()){ this.error='Type your full name to sign as founder.'; return false }
            this.error='';
            this.sig.founder={name:this.signerName, at:new Date().toLocaleString('en-US',{month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'})};
            this.flash('Signed — submitting form…');
            this.$nextTick(()=>this.$refs.submissionForm.requestSubmit());
        },
        flash(m){ this.toast=m; setTimeout(()=>this.toast='',2600) }
     }"
     @open-form-filler.window="start($event.detail)"
     @keydown.escape.window="close()">

    @if (session('status'))
        <div role="status" class="mb-4 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">The form could not be submitted:</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div x-show="open" x-cloak x-transition.opacity @click.self="close()" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-3 backdrop-blur-sm sm:p-6">
        <form x-ref="submissionForm" role="dialog" aria-modal="true" aria-label="Startup form filler" method="POST" action="{{ route('form-submissions.store') }}" enctype="multipart/form-data" class="flex max-h-[94vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            @csrf
            <input type="hidden" name="form_id" :value="formId">
            <input type="hidden" name="startup" :value="startup">
            <input type="hidden" name="track" :value="track">
            <input type="hidden" name="signer_name" :value="signerName">
            <input type="hidden" name="data" :value="submissionData">

            {{-- Header + stepper --}}
            <div class="px-6 pb-4 pt-5 text-white transition-colors" :style="'background:'+accent">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-white/70" x-text="'Form '+formId+' · '+form.category"></p>
                        <h2 class="truncate text-lg font-bold" x-text="form.title"></h2>
                    </div>
                    <button type="button" @click="close()" aria-label="Close form" class="rounded-lg p-1.5 text-white/80 hover:bg-white/15">✕</button>
                </div>
                <ol class="mt-4 grid grid-cols-3 gap-2">
                    <template x-for="(s,i) in steps" :key="s">
                        <li>
                            <div class="h-1.5 rounded-full" :class="i<step ? 'bg-white' : 'bg-white/30'"></div>
                            <p class="mt-1.5 hidden text-[11px] font-semibold sm:block" :class="i+1===step ? 'text-white' : 'text-white/60'" x-text="(i+1)+'. '+s"></p>
                        </li>
                    </template>
                </ol>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto p-6 text-sm">
                <p x-show="error" x-cloak x-transition class="mb-4 rounded-xl bg-red-50 px-3 py-2 text-xs font-semibold text-red-600" x-text="error"></p>

                {{-- STEP 1 --}}
                <div x-show="step===1" class="space-y-5">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Form</label>
                        <select x-model.number="formId" @change="pickForm()" class="w-full rounded-xl border border-slate-line px-3 py-2">
                            <template x-for="c in catalog" :key="c.id"><option :value="c.id" x-text="'Form '+c.id+' — '+c.title"></option></template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Startup</label>
                        <input x-model="startup" placeholder="e.g. AgriSense Labs" class="w-full rounded-xl border border-slate-line px-3 py-2 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                    </div>
                    <div>
                        <p class="mb-2 text-xs font-semibold text-slate-600">Track</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <button type="button" @click="setTrack('internal')" class="rounded-2xl border-2 p-4 text-left transition" :class="track==='internal' ? 'border-[#b92369] bg-[#fcf0f5]' : 'border-gray-200 hover:border-gray-300'">
                                <span class="rounded-lg bg-[#fcf0f5] px-2 py-0.5 text-xs font-semibold text-[#b92369]">Internal Academic</span>
                                <p class="mt-2 text-xs text-gray-500">Adds TTO disclosure questions, campus advisor approval and lab equipment list.</p>
                            </button>
                            <button type="button" @click="setTrack('external')" :disabled="academicOnly" class="rounded-2xl border-2 p-4 text-left transition disabled:cursor-not-allowed disabled:opacity-50" :class="track==='external' ? 'border-[#1a8a8e] bg-[#eaf6f6]' : 'border-gray-200 hover:border-gray-300'">
                                <span class="rounded-lg bg-[#eaf6f6] px-2 py-0.5 text-xs font-semibold text-[#1a8a8e]">External Industry</span>
                                <p class="mt-2 text-xs text-gray-500">Adds SEC / corporate registration ID, commercial tax number and equity ownership structure.</p>
                            </button>
                        </div>
                        <p x-show="academicOnly" x-cloak class="mt-2 text-xs text-[#b92369]">This form is only available on the Academic track.</p>
                    </div>
                </div>

                {{-- STEP 2: track-specific questions and documents required by this form --}}
                <div x-show="step===2" x-cloak>
                    <x-forms.required-form-document />
                    {{-- Internal --}}
                    <div x-show="track==='internal'" class="space-y-5">
                        <p class="rounded-xl bg-[#fcf0f5] px-3 py-2 text-xs font-semibold text-[#b92369]">Academic track · TTO disclosure, campus approvals and lab resources</p>

                        <fieldset class="space-y-3">
                            <legend class="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-400">TTO disclosure questions</legend>
                            <template x-for="q in [{k:'ttoFiled',t:'Has an invention disclosure been filed with the Tech Transfer Office?'},{k:'ttoUniv',t:'Was the technology developed using university facilities or funding?'},{k:'ttoCo',t:'Are any co-inventors university employees?'}]" :key="q.k">
                                <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gray-100 p-3">
                                    <span x-text="q.t" class="max-w-md"></span>
                                    <span class="inline-flex rounded-lg border border-slate-line p-0.5 text-xs font-semibold">
                                        <button type="button" @click="f[q.k]='yes'" class="rounded-md px-3 py-1" :class="f[q.k]==='yes' ? 'bg-[#b92369] text-white' : 'text-gray-600'">Yes</button>
                                        <button type="button" @click="f[q.k]='no'" class="rounded-md px-3 py-1" :class="f[q.k]==='no' ? 'bg-[#b92369] text-white' : 'text-gray-600'">No</button>
                                    </span>
                                </div>
                            </template>
                            <div x-show="f.ttoFiled==='yes'" x-cloak><label class="mb-1 block text-xs font-semibold text-slate-600">TTO disclosure reference no.</label><input x-model="f.ttoRef" class="w-full rounded-xl border border-slate-line px-3 py-2"></div>
                        </fieldset>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div><label class="mb-1 block text-xs font-semibold text-slate-600">Campus advisor</label><input x-model="f.advisor" placeholder="Faculty advisor name" class="w-full rounded-xl border border-slate-line px-3 py-2"></div>
                            <label class="flex items-end gap-2 pb-2"><input type="checkbox" x-model="f.chairOk" class="rounded border-gray-300 text-[#b92369] focus:ring-[#b92369]"> <span>Department chair has approved</span></label>
                        </div>

                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">Lab equipment requested</p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <template x-for="e in equipment" :key="e.n">
                                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border p-2.5 transition" :class="e.on ? 'border-[#b92369] bg-[#fcf0f5]' : 'border-gray-200 hover:border-gray-300'">
                                        <input type="checkbox" x-model="e.on" class="rounded border-gray-300 text-[#b92369] focus:ring-[#b92369]"><span x-text="e.n"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- External --}}
                    <div x-show="track==='external'" x-cloak class="space-y-5">
                        <p class="rounded-xl bg-[#eaf6f6] px-3 py-2 text-xs font-semibold text-[#1a8a8e]">Industry track · Corporate registration, tax and ownership</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div><label class="mb-1 block text-xs font-semibold text-slate-600">SEC / corporate registration ID</label><input x-model="f.secId" class="w-full rounded-xl border border-slate-line px-3 py-2"></div>
                            <div><label class="mb-1 block text-xs font-semibold text-slate-600">Commercial tax number</label><input x-model="f.taxNo" class="w-full rounded-xl border border-slate-line px-3 py-2"></div>
                        </div>
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Private equity ownership structure</p>
                                <button type="button" @click="addOwner()" class="rounded-lg bg-[#eaf6f6] px-2.5 py-1 text-xs font-semibold text-[#1a8a8e] hover:bg-[#d8eeee]">+ Add holder</button>
                            </div>
                            <div class="space-y-2">
                                <template x-for="(o,i) in owners" :key="i">
                                    <div class="grid grid-cols-12 gap-2">
                                        <input x-model="o.name" placeholder="Holder name" class="col-span-5 rounded-xl border border-slate-line px-3 py-2">
                                        <select x-model="o.type" class="col-span-4 rounded-xl border border-slate-line px-2 py-2"><option>Founder</option><option>Investor</option><option>Corporate sponsor</option><option>ESOP</option></select>
                                        <div class="relative col-span-2"><input type="number" min="0" max="100" x-model.number="o.pct" class="w-full rounded-xl border border-slate-line px-3 py-2 pr-6"><span class="absolute right-2.5 top-2 text-gray-400">%</span></div>
                                        <button type="button" @click="owners.splice(i,1)" :disabled="owners.length<2" class="col-span-1 rounded-lg text-gray-400 hover:text-[#b92369] disabled:opacity-30">✕</button>
                                    </div>
                                </template>
                            </div>
                            <div class="mt-3 flex items-center gap-3">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full transition-all" :class="ownerTotal===100 ? 'bg-[#1a8a8e]' : 'bg-[#b92369]'" :style="`width:${Math.min(ownerTotal,100)}%`"></div></div>
                                <span class="text-xs font-semibold" :class="ownerTotal===100 ? 'text-[#1a8a8e]' : 'text-[#b92369]'" x-text="ownerTotal+'% allocated'"></span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- STEP 3: review + signatures --}}
                <div x-show="step===3" x-cloak class="space-y-6">
                    <dl class="grid gap-x-6 gap-y-2 rounded-2xl p-4 text-xs sm:grid-cols-2" :style="'background:'+tint">
                        <div><dt class="text-gray-500">Form</dt><dd class="font-semibold" x-text="form.title"></dd></div>
                        <div><dt class="text-gray-500">Startup</dt><dd class="font-semibold" x-text="startup"></dd></div>
                        <div><dt class="text-gray-500">Track</dt><dd class="font-semibold" x-text="track==='internal' ? 'Internal Academic' : 'External Industry'"></dd></div>
                        <div x-show="track==='internal'"><dt class="text-gray-500">TTO disclosure filed</dt><dd class="font-semibold capitalize" x-text="f.ttoFiled || '—'"></dd></div>
                        <div x-show="track==='internal'"><dt class="text-gray-500">Equipment requested</dt><dd class="font-semibold" x-text="equipment.filter(e=>e.on).length"></dd></div>
                        <div x-show="track==='external'" x-cloak><dt class="text-gray-500">Registration ID</dt><dd class="font-semibold" x-text="f.secId || '—'"></dd></div>
                        <div x-show="track==='external'" x-cloak><dt class="text-gray-500">Equity holders</dt><dd class="font-semibold" x-text="owners.length+' ('+ownerTotal+'%)'"></dd></div>
                    </dl>

                    {{-- Approval flow tracker --}}
                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Signature & approval flow</p>
                        <ol class="space-y-0">
                            <template x-for="(s,i) in flow" :key="s.key">
                                <li class="relative flex gap-4 pb-6 last:pb-0">
                                    <span x-show="i<flow.length-1" class="absolute left-[15px] top-8 h-[calc(100%-2rem)] w-0.5" :class="sig[s.key] ? 'bg-[#1a8a8e]' : 'bg-gray-200'"></span>
                                    <span class="z-[1] flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ring-4 ring-white"
                                          :class="stateOf(i)==='done' ? 'bg-[#1a8a8e] text-white' : stateOf(i)==='current' ? 'text-white' : 'bg-gray-100 text-gray-400'"
                                          :style="stateOf(i)==='current' ? 'background:'+accent : ''"
                                          x-text="stateOf(i)==='done' ? '✓' : i+1"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold" x-text="s.label"></p>
                                        <p class="text-xs text-gray-500" x-text="sig[s.key] ? ('Signed by '+sig[s.key].name+' · '+sig[s.key].at) : (stateOf(i)==='current' ? 'Waiting for '+s.role : 'Pending earlier step')"></p>

                                        <div x-show="s.key==='founder' && !sig.founder" class="mt-2 flex flex-wrap gap-2">
                                            <input x-model="signerName" placeholder="Type full name to sign" class="w-56 rounded-xl border border-slate-line px-3 py-1.5 text-sm italic focus:border-[#1a8a8e] focus:outline-none">
                                            <button type="button" @click="signAndSubmit()" class="rounded-xl bg-[#b92369] px-4 py-1.5 text-xs font-semibold text-white hover:bg-[#9a1b55]">Sign &amp; Submit</button>
                                        </div>
                                    </div>
                                </li>
                            </template>
                        </ol>
                    </div>
                </div>
            </div>

            {{-- Export & sync bar + nav --}}
            <div class="border-t border-gray-100 bg-gray-50 px-6 py-3">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="ml-auto flex gap-2">
                        <button type="button" x-show="step>1" @click="back()" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Back</button>
                        <button type="button" @click="close()" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="button" x-show="step<3" @click="next()" class="rounded-xl px-5 py-2 text-sm font-semibold text-white transition hover:opacity-90" :style="'background:'+accent">Continue</button>
                    </div>
                </div>
            </div>
        </form>

        <div x-show="toast" x-cloak x-transition class="fixed bottom-6 left-1/2 z-[60] -translate-x-1/2 rounded-xl bg-slate-ink px-4 py-2.5 text-sm font-medium text-white shadow-xl" x-text="toast"></div>
    </div>
</div>
