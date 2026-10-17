{{-- Add Contact slide-over (header button: "+ Add Industry Contact"; side can be switched to Internal) --}}
<div x-data="{
        open:false, side:'external', editing:false, contactId:null,
        f:{
            name:'',role:'Industry Mentor',roleOther:'',organizationType:'Company / Corporation',
            organizationTypeOther:'',organizationName:'',academicProgram:'',academicYear:'',
            email:'',phone:'',nda:'Pending',domains:[],domainOther:'',technicalSkills:[],
            technicalOther:'',skillDetails:''
        },
        roles:{
            internal:['University Faculty Advisor','Campus Researcher','Student Lead','Department Chair','TTO Officer','Other'],
            external:['Industry Mentor','Venture Capitalist','Angel Investor','Corporate Partner','Government Grant Officer','Legal/Compliance Advisor','Other']
        },
        organizationTypes:{
            internal:['University department / office','Research lab / center','Student organization / academic unit','Other'],
            external:['Company / Corporation','Startup / Small business','Freelancer / Self-employed','Government agency','Investor / VC fund','Nonprofit / NGO','Professional firm / Consultancy','Other']
        },
        domainOptions:['Health / Life Sciences','Information Technology','Agriculture / Food Systems','Education','Finance / Fintech','Clean Energy / Environment','Engineering / Manufacturing','Business / Entrepreneurship','Legal / Compliance','Other'],
        technicalOptions:['Programming / Software Development','Web Development','Mobile App Development','Data Analytics / Data Science','Artificial Intelligence / Machine Learning','Cybersecurity','Cloud / DevOps','UI / UX Design','Hardware / Electronics / IoT','Research Methods','Product / Project Management','Other'],
        get isStudent(){ return this.side==='internal' && this.f.role==='Student Lead' },
        get isIndependent(){ return this.f.organizationType==='Freelancer / Self-employed' },
        resetForm(){
            this.f={name:'',role:this.roles[this.side][0],roleOther:'',organizationType:this.organizationTypes[this.side][0],organizationTypeOther:'',organizationName:'',academicProgram:'',academicYear:'',email:'',phone:'',nda:'Pending',domains:[],domainOther:'',technicalSkills:[],technicalOther:'',skillDetails:''};
        },
        setSide(s){
            this.side=s;
            this.resetForm();
        },
        editContact(contact){
            this.editing=true;
            this.contactId=contact.id;
            this.side=contact.side;
            this.resetForm();
            this.f.name=contact.name || '';
            this.f.email=contact.email || '';
            this.f.phone=contact.phone || '';
            this.f.organizationName=contact.org || '';
            this.f.academicProgram=contact.academicProgram || '';
            this.f.academicYear=contact.academicYear || '';
            this.f.skillDetails=contact.skillDetails || '';
            this.f.nda=contact.nda || 'Pending';
            this.f.role=this.roles[this.side].includes(contact.role) ? contact.role : 'Other';
            this.f.roleOther=this.f.role==='Other' ? contact.role : '';
            this.f.organizationType=this.organizationTypes[this.side].includes(contact.affiliationType) ? contact.affiliationType : 'Other';
            this.f.organizationTypeOther=this.f.organizationType==='Other' ? contact.affiliationType : '';
            this.f.domains=contact.domains || [];
            this.f.domainOther=contact.domainOther || '';
            if(this.f.domainOther) this.f.domains.push('Other');
            this.f.technicalSkills=contact.technicalSkills || [];
            this.f.technicalOther=contact.technicalOther || '';
            if(this.f.technicalOther) this.f.technicalSkills.push('Other');
            this.open=true;
        },
        submit(){
            if(this.f.role==='Other' && !this.f.roleOther.trim()) return;
            if(this.f.organizationType==='Other' && !this.f.organizationTypeOther?.trim()) return;
            if(this.f.domains.includes('Other') && !this.f.domainOther.trim()) return;
            if(this.f.technicalSkills.includes('Other') && !this.f.technicalOther.trim()) return;
            if (!this.$refs.contactForm.reportValidity()) return;
            this.$refs.contactForm.submit();
        }
     }"
     @open-contact-form.window="editing=false; contactId=null; side='external'; resetForm(); open=true"
     @open-contact-edit.window="editContact($event.detail)"
     @keydown.escape.window="open=false">

    <div x-show="open" x-cloak x-transition.opacity @click="open=false" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm"></div>
    <aside x-show="open" x-cloak
           x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           class="fixed inset-y-0 right-0 z-50 flex h-dvh max-h-dvh min-h-0 w-full max-w-md flex-col overflow-hidden bg-white shadow-2xl"
           style="height:100dvh;max-height:100dvh;min-height:0">
        <div class="shrink-0 px-6 py-5 text-white transition-colors" :style="'background:'+(side==='internal'?'#b92369':'#1a8a8e')">
            <div class="flex items-start justify-between">
                <h2 class="text-lg font-bold" x-text="editing ? 'Edit Contact' : (side==='internal' ? 'Add Internal Contact' : 'Add Industry Contact')"></h2>
                <button @click="open=false" class="rounded-lg p-1.5 text-white/80 hover:bg-white/15">✕</button>
            </div>
            <div class="mt-3 inline-flex rounded-xl bg-white/15 p-0.5 text-xs font-semibold">
                <button @click="setSide('internal')" class="rounded-lg px-3 py-1" :class="side==='internal' ? 'bg-white text-[#b92369]' : 'text-white'">Internal</button>
                <button @click="setSide('external')" class="rounded-lg px-3 py-1" :class="side==='external' ? 'bg-white text-[#1a8a8e]' : 'text-white'">External</button>
            </div>
        </div>
        <form x-ref="contactForm" method="POST" :action="editing ? '{{ url('/contacts') }}/'+contactId : '{{ route('contacts.store') }}'" @submit.prevent="submit()" class="flex min-h-0 flex-1 flex-col" style="display:flex;flex:1 1 0%;flex-direction:column;min-height:0">
        @csrf
        <input type="hidden" name="_method" value="PUT" :disabled="!editing">
        <input type="hidden" name="side" x-model="side">
        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto overscroll-contain px-6 py-6" style="min-height:0;flex:1 1 0%;overflow-y:auto;overscroll-behavior:contain">
            <div>
                <label for="contact-name" class="mb-1 block text-xs font-semibold text-slate-600">Full name</label>
                <input id="contact-name" name="name" x-model="f.name" autocomplete="name" required maxlength="255" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
            </div>
            <div>
                <label for="contact-organization-type" class="mb-1 block text-xs font-semibold text-slate-600">Affiliation type</label>
                <select id="contact-organization-type" name="affiliation_type" x-model="f.organizationType" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
                    <template x-for="type in organizationTypes[side]" :key="type"><option :value="type" x-text="type"></option></template>
                </select>
                <div x-show="f.organizationType==='Other'" x-cloak x-transition class="mt-3">
                    <label for="contact-organization-type-other" class="mb-1 block text-xs font-semibold text-slate-600">Specify affiliation type</label>
                    <input id="contact-organization-type-other" name="affiliation_type_other" x-model="f.organizationTypeOther" x-bind:required="f.organizationType==='Other'" maxlength="100" placeholder="e.g. Cooperative, professional association" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
                </div>
                <div x-show="!isIndependent" x-cloak class="mt-3">
                    <label for="contact-organization-name" class="mb-1 block text-xs font-semibold text-slate-600" x-text="side==='internal' ? 'University / Department / Office' : 'Organization / Company / Fund'"></label>
                    <input id="contact-organization-name" name="organization" x-model="f.organizationName" x-bind:required="!isIndependent" :disabled="isIndependent" maxlength="160" :placeholder="side==='internal' ? 'e.g. College of Engineering' : 'e.g. Meridian Ventures'" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
                </div>
                <div x-show="isIndependent" x-cloak x-transition class="mt-3">
                    <label for="contact-independent-name" class="mb-1 block text-xs font-semibold text-slate-600">Business / professional name <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="contact-independent-name" name="organization" x-model="f.organizationName" :disabled="!isIndependent" maxlength="160" placeholder="Leave blank if you work under your own name" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label for="contact-role-select" class="mb-1 block text-xs font-semibold text-slate-600">Role</label>
                <select id="contact-role-select" name="role" x-model="f.role" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
                    <template x-for="r in roles[side]" :key="r"><option :value="r" x-text="r"></option></template>
                </select>
                <div x-show="f.role==='Other'" x-cloak x-transition class="mt-3">
                    <label for="contact-role-other" class="mb-1 block text-xs font-semibold text-slate-600">Specify role</label>
                    <input id="contact-role-other" name="role_other" x-model="f.roleOther" x-bind:required="f.role==='Other'" :disabled="f.role!=='Other'"
                           maxlength="120" placeholder="Enter the contact's role"
                           class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                </div>
            </div>
            <div x-show="isStudent" x-cloak x-transition class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="contact-academic-program" class="mb-1 block text-xs font-semibold text-slate-600">Program / Course</label>
                    <input id="contact-academic-program" name="academic_program" x-model="f.academicProgram" x-bind:required="isStudent" :disabled="!isStudent" maxlength="120" placeholder="e.g. BS Computer Science" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="contact-academic-year" class="mb-1 block text-xs font-semibold text-slate-600">Year level</label>
                    <select id="contact-academic-year" name="academic_year" x-model="f.academicYear" x-bind:required="isStudent" :disabled="!isStudent" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
                        <option value="">Select year</option><option>1st year</option><option>2nd year</option><option>3rd year</option><option>4th year</option><option>5th year or higher</option><option>Graduate student</option>
                    </select>
                </div>
            </div>
            <fieldset class="space-y-3">
                <legend class="text-xs font-semibold text-slate-600">Domain expertise <span class="font-normal text-slate-400">(select all that apply)</span></legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    <template x-for="domain in domainOptions" :key="domain">
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-700 hover:border-[#1a8a8e]/40 hover:bg-[#eaf6f6]/50">
                            <input type="checkbox" name="domains[]" :value="domain" x-model="f.domains" class="rounded border-slate-300 text-[#1a8a8e] focus:ring-[#1a8a8e]">
                            <span x-text="domain"></span>
                        </label>
                    </template>
                </div>
                <div x-show="f.domains.includes('Other')" x-cloak x-transition>
                    <label for="contact-domain-other" class="mb-1 block text-xs font-semibold text-slate-600">Specify domain</label>
                    <input id="contact-domain-other" name="domain_other" x-model="f.domainOther" x-bind:required="f.domains.includes('Other')" :disabled="!f.domains.includes('Other')" maxlength="120" placeholder="Enter another area of expertise" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
                </div>
            </fieldset>
            <fieldset class="space-y-3">
                <legend class="text-xs font-semibold text-slate-600">Technical skills <span class="font-normal text-slate-400">(optional — choose specific skills)</span></legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    <template x-for="skill in technicalOptions" :key="skill">
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-700 hover:border-[#b92369]/40 hover:bg-[#fcf0f5]/50">
                            <input type="checkbox" name="technical_skills[]" :value="skill" x-model="f.technicalSkills" class="rounded border-slate-300 text-[#b92369] focus:ring-[#b92369]">
                            <span x-text="skill"></span>
                        </label>
                    </template>
                </div>
                <div x-show="f.technicalSkills.includes('Other')" x-cloak x-transition>
                    <label for="contact-technical-other" class="mb-1 block text-xs font-semibold text-slate-600">Specify technical skill</label>
                    <input id="contact-technical-other" name="technical_other" x-model="f.technicalOther" x-bind:required="f.technicalSkills.includes('Other')" :disabled="!f.technicalSkills.includes('Other')" maxlength="160" placeholder="Add another specific skill" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="contact-skill-details" class="mb-1 block text-xs font-semibold text-slate-600">Tools, languages or methods <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="contact-skill-details" name="skill_details" x-model="f.skillDetails" maxlength="200" placeholder="e.g. Python, JavaScript, React, Laravel, SQL" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
                </div>
            </fieldset>
            <div>
                <label for="contact-email" class="mb-1 block text-xs font-semibold text-slate-600">Email</label>
                <input id="contact-email" name="email" x-model="f.email" type="email" autocomplete="email" required maxlength="255" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="contact-phone" class="mb-1 block text-xs font-semibold text-slate-600">Phone number <span class="font-normal text-slate-400">(optional)</span></label>
                <input id="contact-phone" name="phone" x-model="f.phone" type="tel" autocomplete="tel" maxlength="40" placeholder="e.g. +63 917 123 4567" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm">
            </div>
            <div><label class="block mb-1 text-xs font-semibold text-slate-600">NDA / MOU status</label>
                <select name="nda_status" x-model="f.nda" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm"><option>Pending</option><option>NDA Signed</option><option>MOU Signed</option><option>Both Signed</option></select></div>
        </div>
        <div class="flex shrink-0 justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4">
            <button type="button" @click="open=false" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Cancel</button>
            <button type="submit" :disabled="(f.role==='Other' && !f.roleOther.trim()) || (f.organizationType==='Other' && !f.organizationTypeOther?.trim()) || (isStudent && (!f.academicProgram.trim() || !f.academicYear)) || (f.domains.includes('Other') && !f.domainOther.trim()) || (f.technicalSkills.includes('Other') && !f.technicalOther.trim())"
                    class="rounded-xl px-5 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    :style="'background:'+(side==='internal'?'#b92369':'#1a8a8e')" x-text="editing ? 'Save Changes' : 'Save Contact'"></button>
        </div>
        </form>
    </aside>
</div>
