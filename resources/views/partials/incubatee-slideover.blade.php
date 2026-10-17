{{-- Add / Edit Incubatee slide-over: 3-step wizard, fields adapt to Internal vs External track --}}
<div x-data="{
        open:false, step:1, track:null,
        f:{name:'',founder:'',email:'',sector:'Agritech',subtype:'',status:'Applicant',
           tto:'Disclosure Filed',dept:'',equity:'',lab:'',
           entity:'Inc.',stage:'Pre-Seed',revenue:'< $50k',sponsor:''},
        internalTypes:['Faculty Spin-off','Student Venture','Campus Research Lab','University IP Licensing'],
        externalTypes:['Industry Startup','Corporate Spin-out','Regional Business','Cross-Border Accelerator'],
        get types(){ return this.track==='internal' ? this.internalTypes : this.externalTypes },
        get accent(){ return this.track==='external' ? '#1a8a8e' : '#b92369' },
        pick(t){ this.track=t; this.f.subtype=''; this.step=2 },
        reset(){ this.step=1; this.track=null },
        submit(){ alert('Mock submit — POST to your controller:\n'+JSON.stringify({track:this.track,...this.f},null,2)); this.open=false; this.reset() }
     }"
     @keydown.escape.window="open=false">

    <div x-show="open" x-cloak x-transition.opacity @click="open=false" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm"></div>

    <aside x-show="open" x-cloak
           x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           class="fixed inset-y-0 right-0 z-50 flex w-full max-w-xl flex-col bg-white shadow-2xl">

        {{-- Header --}}
        <div class="relative overflow-hidden px-6 py-5 text-white" :style="track==='external' ? 'background:#1a8a8e' : track==='internal' ? 'background:#b92369' : ''" :class="{'grad':!track}">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-white/70">Step <span x-text="step"></span> of 3</p>
                    <h2 class="mt-0.5 text-lg font-bold">Add Incubatee</h2>
                </div>
                <button @click="open=false" class="rounded-lg p-1.5 text-white/80 hover:bg-white/15">✕</button>
            </div>
            <div class="mt-4 flex gap-1.5">
                <template x-for="i in 3"><span class="h-1.5 flex-1 rounded-full" :class="i<=step ? 'bg-white' : 'bg-white/30'"></span></template>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-6">
            {{-- Step 1: track --}}
            <div x-show="step===1" class="space-y-4">
                <p class="text-sm text-gray-600">Which ecosystem does this entity belong to? Required fields change accordingly.</p>
                <button @click="pick('internal')" class="group w-full rounded-2xl border-2 border-gray-200 p-5 text-left transition hover:border-[#b92369] hover:bg-[#fcf0f5]">
                    <span class="inline-flex rounded-lg bg-[#fcf0f5] px-2 py-0.5 text-xs font-semibold text-[#b92369]">Internal Track</span>
                    <p class="mt-2 font-semibold">Internal Academic</p>
                    <p class="text-sm text-gray-500">Faculty/student spin-offs, campus labs, university IP licensing.</p>
                </button>
                <button @click="pick('external')" class="group w-full rounded-2xl border-2 border-gray-200 p-5 text-left transition hover:border-[#1a8a8e] hover:bg-[#eaf6f6]">
                    <span class="inline-flex rounded-lg bg-[#eaf6f6] px-2 py-0.5 text-xs font-semibold text-[#1a8a8e]">External Track</span>
                    <p class="mt-2 font-semibold">External Industry</p>
                    <p class="text-sm text-gray-500">Regional startups, corporate spin-outs, cross-border accelerators.</p>
                </button>
            </div>

            {{-- Step 2: core details --}}
            <div x-show="step===2" x-cloak class="space-y-4">
                <template x-if="true">
                    <div class="space-y-4">
                        <div><label class="lbl">Startup name</label><input x-model="f.name" class="inp" placeholder="e.g. AgriSense Labs"></div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="lbl">Lead founder</label><input x-model="f.founder" class="inp"></div>
                            <div><label class="lbl">Email</label><input x-model="f.email" type="email" class="inp"></div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="lbl" x-text="track==='internal' ? 'Venture type (internal)' : 'Venture type (external)'"></label>
                                <select x-model="f.subtype" class="inp"><option value="">Select…</option><template x-for="t in types" :key="t"><option x-text="t"></option></template></select></div>
                            <div><label class="lbl">Industry sector</label>
                                <select x-model="f.sector" class="inp"><option>Agritech</option><option>Fintech</option><option>Healthtech</option><option>Edtech</option><option>AI/Deeptech</option><option>Clean Energy</option></select></div>
                        </div>
                        <div><label class="lbl">Pipeline status</label>
                            <select x-model="f.status" class="inp"><option>Applicant</option><option>Incubatee</option><option>Incubatee Extended</option><option>Graduate</option><option>Alumni Network</option></select></div>
                    </div>
                </template>
            </div>

            {{-- Step 3: track-specific metadata --}}
            <div x-show="step===3" x-cloak class="space-y-4">
                <div x-show="track==='internal'" class="space-y-4">
                    <p class="rounded-xl bg-[#fcf0f5] px-3 py-2 text-xs font-medium text-[#b92369]">Academic details · Tech Transfer &amp; university terms</p>
                    <div><label class="lbl">TTO status</label>
                        <select x-model="f.tto" class="inp"><option>Disclosure Filed</option><option>Under Evaluation</option><option>Patent Pending</option><option>License Signed</option></select></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="lbl">Campus department</label><input x-model="f.dept" class="inp" placeholder="Dept. of Agronomy"></div>
                        <div><label class="lbl">University equity / royalty %</label><input x-model="f.equity" type="number" min="0" max="100" class="inp" placeholder="5"></div>
                    </div>
                    <div><label class="lbl">Lab location</label><input x-model="f.lab" class="inp" placeholder="Science Bldg, Rm 204"></div>
                </div>
                <div x-show="track==='external'" class="space-y-4">
                    <p class="rounded-xl bg-[#eaf6f6] px-3 py-2 text-xs font-medium text-[#1a8a8e]">Commercial details · Entity &amp; funding</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="lbl">Legal entity type</label>
                            <select x-model="f.entity" class="inp"><option>Inc.</option><option>LLC</option><option>Corp</option></select></div>
                        <div><label class="lbl">Funding stage</label>
                            <select x-model="f.stage" class="inp"><option>Pre-Seed</option><option>Seed</option><option>Series A</option></select></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="lbl">Revenue tier</label>
                            <select x-model="f.revenue" class="inp"><option>&lt; $50k</option><option>$50k – $250k</option><option>$250k – $1M</option><option>$1M+</option></select></div>
                        <div><label class="lbl">Corporate sponsor</label><input x-model="f.sponsor" class="inp" placeholder="Optional"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50 px-6 py-4" x-show="step>1" x-cloak>
            <button @click="step--" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Back</button>
            <button x-show="step===2" @click="step=3" class="rounded-xl px-5 py-2 text-sm font-semibold text-white" :style="'background:'+accent">Continue</button>
            <button x-show="step===3" @click="submit()" class="rounded-xl px-5 py-2 text-sm font-semibold text-white" :style="'background:'+accent">Save Incubatee</button>
        </div>
    </aside>

    <style>
        .lbl{display:block;margin-bottom:.35rem;font-size:.75rem;font-weight:600;color:#475569}
        .inp{width:100%;border-radius:.75rem;border:1px solid #E2E8F0;background:#fff;padding:.55rem .75rem;font-size:.875rem}
        .inp:focus{outline:none;border-color:#1a8a8e;box-shadow:0 0 0 3px rgba(26,138,142,.15)}
    </style>
</div>
