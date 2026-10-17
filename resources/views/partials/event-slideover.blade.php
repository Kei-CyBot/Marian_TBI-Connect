{{-- Create / Edit Event drawer. Open with: $dispatch('open-event-form')  or  $dispatch('open-event-form', eventObject) --}}
<div x-data="{
        open:false, editing:false,
        types:{
            internal:['Campus Hackathon','TTO Commercialization Workshop','Student Founder Bootcamp','Internal Demo Day','Other'],
            external:['VC Investor Pitch Night','Corporate Partner Matchmaking','Regional Startup Summit','Industry Masterclass','Other'],
            hybrid:['Open Innovation Expo','Public Demo Day','Other']
        },
        f:{title:'',scope:'internal',kind:'',otherKind:'',date:'',time:'',mode:'physical',loc:'',host:'',cohort:''},
        get accent(){ return this.f.scope==='internal' ? '#b92369' : this.f.scope==='external' ? '#1a8a8e' : null },
        kindOptions(){ return this.types[this.f.scope] || [] },
        load(e){
            this.editing = !!(e && e.id);
            const options=this.types[e?.scope] || [];
            const knownKind=e?.kind && options.includes(e.kind);
            this.f = e && e.id
                ? {id:e.id,title:e.title,scope:e.scope,kind:knownKind ? e.kind : 'Other',otherKind:knownKind ? '' : e.kind,date:e.date,time:e.time,mode:e.virtual?'virtual':'physical',loc:e.loc,host:e.host,cohort:e.cohort}
                : {id:null,title:'',scope:'internal',kind:'',otherKind:'',date:'',time:'',mode:'physical',loc:'',host:'',cohort:''};
            this.open = true;
        },
        submit(){
            if(this.f.kind==='Other' && !this.f.otherKind.trim()){
                alert('Enter the event type.');
                return;
            }
            this.$dispatch('event-form-save',{...this.f,kind:this.f.kind==='Other' ? this.f.otherKind.trim() : this.f.kind,virtual:this.f.mode==='virtual'});
            this.open=false;
        }
     }"
     @open-event-form.window="load($event.detail)"
     @keydown.escape.window="open=false">

    <div x-show="open" x-cloak x-transition.opacity @click="open=false" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm"></div>
    <aside x-show="open" x-cloak
           x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-white shadow-2xl">
        <div class="px-6 py-5 text-white transition-colors" :class="{'grad':!accent}" :style="accent ? 'background:'+accent : ''">
            <div class="flex items-start justify-between">
                <h2 class="text-lg font-bold" x-text="editing ? 'Edit Event' : 'Create Event'"></h2>
                <button @click="open=false" class="rounded-lg p-1.5 text-white/80 hover:bg-white/15">✕</button>
            </div>
            <div class="mt-3 inline-flex rounded-xl bg-white/15 p-0.5 text-xs font-semibold">
                <button @click="f.scope='internal'; f.kind=''; f.otherKind=''" class="rounded-lg px-3 py-1" :class="f.scope==='internal' ? 'bg-white text-[#b92369]' : 'text-white'">Internal</button>
                <button @click="f.scope='external'; f.kind=''; f.otherKind=''" class="rounded-lg px-3 py-1" :class="f.scope==='external' ? 'bg-white text-[#1a8a8e]' : 'text-white'">External</button>
                <button @click="f.scope='hybrid'; f.kind=''; f.otherKind=''" class="rounded-lg px-3 py-1" :class="f.scope==='hybrid' ? 'bg-white text-slate-ink' : 'text-white'">Hybrid</button>
            </div>
        </div>

        <div class="flex-1 space-y-4 overflow-y-auto px-6 py-6 text-sm">
            <div><label class="mb-1 block text-xs font-semibold text-slate-600">Event name</label><input x-model="f.title" class="w-full rounded-xl border border-slate-line px-3 py-2 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15"></div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-600">Event type</label>
                <select x-model="f.kind" class="w-full rounded-xl border border-slate-line px-3 py-2"><option value="">Select…</option><template x-for="t in kindOptions()" :key="t"><option :value="t" x-text="t"></option></template></select></div>
            <div x-show="f.kind==='Other'" x-cloak><label class="mb-1 block text-xs font-semibold text-slate-600">Specify event type</label><input x-model="f.otherKind" maxlength="120" required placeholder="Enter event type" class="w-full rounded-xl border border-slate-line px-3 py-2"></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">Date</label><input type="date" x-model="f.date" class="w-full rounded-xl border border-slate-line px-3 py-2"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">Time</label><input x-model="f.time" placeholder="09:00 AM" class="w-full rounded-xl border border-slate-line px-3 py-2"></div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Location</label>
                <div class="mb-2 inline-flex rounded-xl border border-slate-line p-0.5 text-xs font-semibold">
                    <button @click="f.mode='physical'" class="rounded-lg px-3 py-1" :class="f.mode==='physical' ? 'bg-slate-ink text-white' : 'text-gray-600'">Physical room</button>
                    <button @click="f.mode='virtual'" class="rounded-lg px-3 py-1" :class="f.mode==='virtual' ? 'bg-slate-ink text-white' : 'text-gray-600'">Virtual (Zoom)</button>
                </div>
                <input x-model="f.loc" :placeholder="f.mode==='virtual' ? 'https://zoom.us/j/…' : 'Building, room'" class="w-full rounded-xl border border-slate-line px-3 py-2">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">Host / organizer</label><input x-model="f.host" class="w-full rounded-xl border border-slate-line px-3 py-2"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-600">Target cohort</label><input x-model="f.cohort" placeholder="Student Founders" class="w-full rounded-xl border border-slate-line px-3 py-2"><p class="mt-1 text-xs leading-5 text-gray-600">Labels the intended audience (for example, a startup cohort or all incubatees) to help staff plan invitations. It does not change incubatee membership or automatically send invitations or filter RSVPs.</p></div>
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4">
            <button @click="open=false" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Cancel</button>
            <button @click="submit()" class="rounded-xl bg-[#b92369] px-5 py-2 text-sm font-semibold text-white hover:bg-[#9a1b55]" x-text="editing ? 'Save changes' : 'Create event'"></button>
        </div>
    </aside>
</div>
