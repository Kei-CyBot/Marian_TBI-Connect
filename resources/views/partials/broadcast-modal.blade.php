<div x-data="{
        open:false,
        contacts:@js($broadcastContacts ?? []),
        mailConfigured:@js($mailConfigured ?? false),
        selectedContactIds:[],
        search:'',
        contactSide:'all',
        eventTitle:null,
        template:'',
        subject:'',
        body:'',
        get selectedContacts(){
            return this.contacts.filter(contact=>this.selectedContactIds.includes(String(contact.id)));
        },
        get visibleContacts(){
            const query=this.search.trim().toLowerCase();
            return this.contacts.filter(contact=>(this.contactSide==='all' || contact.side===this.contactSide) && (!query || contact.name.toLowerCase().includes(query) || contact.email.toLowerCase().includes(query)));
        },
        get allVisibleSelected(){
            return this.visibleContacts.length>0 && this.visibleContacts.every(contact=>this.selectedContactIds.includes(String(contact.id)));
        },
        toggleVisibleContacts(){
            const visibleIds=this.visibleContacts.map(contact=>String(contact.id));
            if(this.allVisibleSelected){
                this.selectedContactIds=this.selectedContactIds.filter(id=>!visibleIds.includes(id));
            }else{
                this.selectedContactIds=[...new Set([...this.selectedContactIds,...visibleIds])];
            }
        },
        get ready(){
            return this.mailConfigured && this.selectedContactIds.length>0 && this.subject.trim() && this.body.trim();
        },
        show(details){
            this.open=true;
            this.selectedContactIds=details && details.contactId ? [String(details.contactId)] : [];
            this.search='';
            this.contactSide='all';
            this.eventTitle=details && details.title ? details.title : null;
            this.template=this.eventTitle ? 'invite' : '';
            this.applyTemplate(this.template);
        },
        applyTemplate(value){
            if(value==='invite'){
                this.subject=this.eventTitle ? 'Invitation: '+this.eventTitle : 'You are invited';
                this.body=this.eventTitle
                    ? 'Hello,\\n\\nYou are invited to '+this.eventTitle+'. We hope you can join us.\\n\\nBest,\\nThe Marian TBI Connect Team'
                    : 'Hello,\\n\\nYou are invited to join us.\\n\\nBest,\\nThe Marian TBI Connect Team';
            }else if(value==='newsletter'){
                this.subject='Marian TBI Connect update';
                this.body='Hello,\\n\\nHere is the latest update from Marian TBI Connect.\\n\\nBest,\\nThe Marian TBI Connect Team';
            }else if(value==='review'){
                this.subject='Incubation progress review';
                this.body='Hello,\\n\\nThis is a reminder about your upcoming incubation progress review.\\n\\nBest,\\nThe Marian TBI Connect Team';
            }else{
                this.subject='';
                this.body='';
            }
        }
     }"
     @open-broadcast.window="show($event.detail)"
     @keydown.escape.window="open=false">
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
        <section role="dialog" aria-modal="true" aria-labelledby="contact-email-title" class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl" @click.outside="open=false">
            <header class="grad flex items-start justify-between px-6 py-5 text-white">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-white/75">Individual contact email</p>
                    <h2 id="contact-email-title" class="mt-1 text-lg font-bold" x-text="eventTitle ? 'Email contacts · '+eventTitle : 'Compose email to contacts'"></h2>
                </div>
                <button type="button" @click="open=false" aria-label="Close email composer" class="rounded-lg p-1.5 text-white/90 hover:bg-white/15">✕</button>
            </header>

            <form method="POST" action="{{ route('event-emails.send') }}" class="flex min-h-0 flex-1 flex-col">
                @csrf
                <div class="grid min-h-0 flex-1 overflow-y-auto md:grid-cols-5">
                    <div class="space-y-4 p-5 md:col-span-2 md:border-r md:border-gray-100">
                        <div>
                            <label for="contact-email-search" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Choose contacts</label>
                            <input id="contact-email-search" x-model="search" type="search" placeholder="Search name or email" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                        </div>
                        <div>
                            <label for="contact-email-side" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Contact group</label>
                            <select id="contact-email-side" x-model="contactSide" class="w-full rounded-xl border border-slate-line bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                                <option value="all">All contacts</option>
                                <option value="internal">Internal</option>
                                <option value="external">External</option>
                            </select>
                        </div>
                        <button type="button" @click="toggleVisibleContacts()" :disabled="!visibleContacts.length" class="w-full rounded-xl border border-[#1a8a8e]/30 bg-[#eaf6f6] px-3 py-2 text-sm font-semibold text-[#146e72] transition hover:bg-[#d9f0f0] disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-text="allVisibleSelected ? 'Deselect visible contacts' : 'Select all visible contacts'"></span>
                        </button>
                        <p class="text-xs text-gray-500">Each selected contact receives a separate email; recipients cannot see each other.</p>
                        <div class="max-h-72 space-y-1 overflow-y-auto rounded-xl border border-gray-100 p-2">
                            <template x-for="contact in visibleContacts" :key="contact.id">
                                <label class="flex cursor-pointer items-start gap-2 rounded-lg p-2 hover:bg-gray-50">
                                    <input type="checkbox" name="contact_ids[]" :value="String(contact.id)" x-model="selectedContactIds" class="mt-0.5 rounded border-gray-300 text-[#1a8a8e] focus:ring-[#1a8a8e]">
                                    <span class="min-w-0"><span class="block truncate text-sm font-medium text-slate-800" x-text="contact.name"></span><span class="block truncate text-xs text-gray-500" x-text="contact.email"></span></span>
                                </label>
                            </template>
                            <p x-show="!visibleContacts.length" class="p-3 text-sm text-gray-500">No contacts with email addresses match this group and search.</p>
                        </div>
                        <p class="text-xs font-semibold text-[#146e72]"><span x-text="selectedContactIds.length"></span> selected</p>
                    </div>

                    <div class="space-y-4 p-5 md:col-span-3">
                        <div>
                            <label for="contact-email-template" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Compose</label>
                            <select id="contact-email-template" x-model="template" @change="applyTemplate(template)" class="w-full rounded-xl border border-slate-line bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                                <option value="">Choose message type</option>
                                <option value="invite">Event invitation</option>
                                <option value="newsletter">Program update</option>
                                <option value="review">Progress review reminder</option>
                                <option value="other">Other (custom message)</option>
                            </select>
                        </div>
                        <input name="subject" x-model="subject" required maxlength="255" placeholder="Subject line" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                        <textarea name="body" x-model="body" required maxlength="20000" rows="12" placeholder="Write your message…" class="w-full rounded-xl border border-slate-line px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15"></textarea>
                        <p x-show="!mailConfigured" class="rounded-xl bg-amber-50 p-3 text-xs leading-5 text-amber-900">Outgoing email is not configured. Ask an administrator to configure the server mailer before sending.</p>
                    </div>
                </div>
                <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 bg-gray-50 px-5 py-4">
                    <p class="text-xs text-gray-500">Send will deliver one private message per selected contact.</p>
                    <div class="flex gap-2">
                        <button type="button" @click="open=false" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Cancel</button>
                        <button type="submit" :disabled="!ready" class="rounded-xl bg-[#1a8a8e] px-5 py-2 text-sm font-semibold text-white hover:bg-[#146e72] disabled:cursor-not-allowed disabled:opacity-40">Send to <span x-text="selectedContactIds.length"></span> contact(s)</button>
                    </div>
                </footer>
            </form>
        </section>
    </div>
</div>
