@extends('layouts.app')
@section('title','File Drive & Documents')
@section('eyebrow','Document Management')
@section('heading','File Drive & Documents')
@section('subheading','Forms, uploaded attachments, and Google Drive links are organized together in each incubatee folder.')
@section('banner-actions')
    @if (auth()->user()->canManageRecords() && $driveConnected)
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-lg bg-white/15 px-3 py-2 text-xs font-semibold text-white">Drive connected · {{ $driveAccountEmail }}</span>
            <form method="POST" action="{{ route('google-drive.sync') }}">
                @csrf
                <button type="submit" class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-[#146e72] shadow-sm hover:bg-[#eaf6f6]">Sync to Google Drive</button>
            </form>
        </div>
    @elseif (auth()->user()->canManageRecords() && $driveConfigured)
        <a href="{{ route('google-drive.connect') }}" class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-[#146e72] shadow-sm hover:bg-[#eaf6f6]">Connect Marian TBI Drive</a>
    @elseif (auth()->user()->canManageRecords())
        <span class="rounded-lg bg-white/15 px-3 py-2 text-xs font-semibold text-white">Google Drive setup required</span>
    @endif
@endsection

@section('content')
@if (session('status'))
    <div role="status" class="mb-4 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('status') }}</div>
@endif
@if (session('drive_sync_warning'))
    <div role="alert" class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">{{ session('drive_sync_warning') }}</div>
@endif
<div role="note" class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
    @if ($driveConnected)
        <strong>App and Google Drive permissions are connected.</strong> Folder access changes and submitted forms are synchronized using the dedicated Marian TBI Google account.
    @elseif (auth()->user()->canManageRecords())
        <strong>App folder access is enforced here.</strong> Connect the dedicated Marian TBI account to also mirror folder permissions and sync form responses and attachments to Drive.
    @else
        <strong>App folder access is enforced here.</strong> Google Drive syncing is managed by an administrator.
    @endif
</div>

<div x-data="{
        tab:@js($openTrack),
        submitted:@js($savedSubmissions),
        driveLinks:@js($driveLinks),
        startupFolders:@js($startupFolders),
        teamUsers:@js($teamUsers),
        currentFolder:@js($openStartupSlug),
        drawer:null,
        accessModal:false,
        accessStartup:null,
        restrictedDraft:false,
        selectedUsers:[],
        get folders(){
            const grouped=new Map();
            this.startupFolders.filter(startup=>startup.track===this.tab).forEach(startup=>{
                grouped.set(startup.slug,{...startup,count:0});
            });
            this.submitted.filter(form=>form.track===this.tab).forEach(form=>{
                if(!grouped.has(form.startupSlug)){
                    grouped.set(form.startupSlug,{id:null,slug:form.startupSlug,name:form.startup,track:form.track,count:0});
                }
                grouped.get(form.startupSlug).count++;
            });
            this.driveLinks.forEach(link=>{
                const folder=this.startupFolders.find(startup=>String(startup.id)===String(link.startupId));
                if(folder && folder.track===this.tab && grouped.has(folder.slug)) grouped.get(folder.slug).count++;
            });
            return Array.from(grouped.values()).sort((a,b)=>a.name.localeCompare(b.name));
        },
        get folderFiles(){
            const forms=this.submitted
                .filter(form=>form.track===this.tab && form.startupSlug===this.currentFolder)
                .map(form=>({
                    id:form.id,
                    name:form.attachmentName || form.title,
                    type:form.attachmentName ? 'Form attachment' : 'Saved form response',
                    owner:form.userName,
                    updated:form.date,
                    isForm:true,
                    status:form.status,
                    data:form.data,
                    attachmentUrl:form.attachmentUrl,
                    driveResponseUrl:form.driveResponseUrl,
                    driveAttachmentUrl:form.driveAttachmentUrl,
                    driveUrl:form.driveAttachmentUrl || form.driveResponseUrl
                }));
            const links=this.driveLinks
                .filter(link=>link.startupSlug===this.currentFolder)
                .map(link=>({
                    id:'drive-'+link.id,
                    name:link.label,
                    type:'Google Drive link',
                    owner:link.userName,
                    updated:link.date,
                    isDriveLink:true,
                    url:link.url
                }));
            return [...forms,...links];
        },
        get currentFolderName(){ return this.folders.find(folder=>folder.slug===this.currentFolder)?.name || this.currentFolder },
        get currentFolderRecord(){ return this.folders.find(folder=>folder.slug===this.currentFolder) || null },
        openAccess(folder){
            if(!folder || !folder.canManageAccess || !folder.id) return;
            this.accessStartup=folder;
            this.restrictedDraft=folder.restricted;
            this.selectedUsers=(folder.accessUserIds || []).map(String);
            this.accessModal=true;
        },
        openFolder(slug){ this.currentFolder=slug; this.drawer=null },
        goToRoot(){ this.currentFolder=null; this.drawer=null; this.accessModal=false }
     }"
     @keydown.escape.window="drawer=null">

    <div class="mb-5 inline-flex flex-wrap rounded-2xl border border-slate-line bg-white p-1 text-sm font-semibold">
        <button type="button" @click="tab='internal'; currentFolder=null" class="rounded-xl px-4 py-2" :class="tab==='internal' ? 'bg-[#146e72] text-white shadow' : 'text-gray-700 hover:bg-gray-50'">Internal Academic</button>
        <button type="button" @click="tab='external'; currentFolder=null" class="rounded-xl px-4 py-2" :class="tab==='external' ? 'bg-[#146e72] text-white shadow' : 'text-gray-700 hover:bg-gray-50'">External Industry</button>
    </div>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-3 text-sm">
            <nav aria-label="File directory breadcrumb" class="flex flex-wrap items-center gap-2 font-semibold text-gray-700">
                <button type="button" @click="goToRoot()" class="hover:underline">Incubatee folders</button>
                <template x-if="currentFolder"><span class="flex items-center gap-2"><span aria-hidden="true">/</span><span class="font-medium" x-text="currentFolderName"></span></span></template>
            </nav>
            <div class="flex items-center gap-3">
                <span x-show="currentFolderRecord?.restricted" class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">Restricted folder</span>
                @if (auth()->user()->canManageRecords())
                    <button x-show="currentFolderRecord?.canManageAccess" x-cloak type="button" @click="openAccess(currentFolderRecord)" class="rounded-lg border border-[#1a8a8e]/30 bg-white px-3 py-1.5 text-xs font-semibold text-[#146e72] hover:bg-[#eaf6f6]">Manage access</button>
                @endif
                <a href="{{ route('incubatees') }}" class="text-xs font-semibold text-[#146e72] hover:underline">Manage Drive links</a>
            </div>
        </div>
        <div x-show="!currentFolder" class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
            <template x-for="folder in folders" :key="folder.slug">
                <button type="button" @click="openFolder(folder.slug)" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 text-left transition hover:border-[#1a8a8e] hover:bg-[#f4fbfb]">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#eaf6f6] text-[#146e72]" aria-hidden="true">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-slate-900" x-text="folder.name"></span>
                        <span class="mt-1 block text-xs text-gray-700" x-text="folder.count+' '+(folder.count===1 ? 'document' : 'documents')"></span>
                        <span x-show="folder.restricted" class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-900">Restricted</span>
                    </span>
                    <span aria-hidden="true" class="text-gray-500">›</span>
                </button>
            </template>
            <p x-show="!folders.length" class="col-span-full rounded-xl border border-dashed border-gray-300 px-4 py-12 text-center text-sm text-gray-700">No incubatees are available in this track.</p>
        </div>

        <div x-show="currentFolder" class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                    <tr><th class="px-5 py-3">Document</th><th class="px-3 py-3">Type</th><th class="px-3 py-3">Added by</th><th class="px-3 py-3">Updated</th><th class="px-5 py-3 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="file in folderFiles" :key="file.id">
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3"><button type="button" @click="drawer=file" class="text-left font-medium text-slate-900 hover:text-[#146e72]" x-text="file.name"></button><p x-show="file.isForm" class="mt-1 text-xs text-gray-600" x-text="'Status: '+file.status"></p></td>
                            <td class="px-3 py-3 text-gray-700" x-text="file.type"></td>
                            <td class="px-3 py-3 text-gray-700" x-text="file.owner || '—'"></td>
                            <td class="px-3 py-3 text-gray-700" x-text="file.updated || '—'"></td>
                            <td class="px-5 py-3 text-right">
                                <a x-show="file.isForm && file.attachmentUrl" x-cloak :href="file.attachmentUrl" class="inline-flex rounded-lg bg-[#146e72] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#10595c]">Download</a>
                                <a x-show="file.isForm && file.driveUrl" x-cloak :href="file.driveUrl" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-lg border border-[#1a8a8e]/30 bg-[#eaf6f6] px-3 py-1.5 text-xs font-semibold text-[#146e72] hover:bg-[#d8eeee]">Open synced copy</a>
                                <a x-show="file.isDriveLink" x-cloak :href="file.url" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-lg bg-[#146e72] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#10595c]">Open in Drive</a>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!folderFiles.length" x-cloak><td colspan="5" class="px-5 py-12 text-center text-sm text-gray-700">No submitted forms, attachments, or Drive links have been added to this incubatee folder yet.</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <div x-show="drawer" x-cloak x-transition.opacity @click="drawer=null" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm"></div>
    <aside x-show="drawer" x-cloak x-transition class="fixed inset-y-0 right-0 z-50 flex w-full max-w-lg flex-col bg-white shadow-2xl">
        <template x-if="drawer">
            <div class="flex h-full flex-col">
                <header class="bg-[#146e72] px-6 py-5 text-white">
                    <div class="flex items-start justify-between gap-3"><p class="font-semibold leading-snug" x-text="drawer.name"></p><button type="button" @click="drawer=null" aria-label="Close details" class="rounded-lg p-1 text-white/90 hover:bg-white/15">✕</button></div>
                    <p class="mt-1 text-xs text-white/90" x-text="drawer.type"></p>
                </header>
                <div class="flex-1 overflow-y-auto p-6 text-sm">
                    <template x-if="drawer.isForm">
                        <div class="space-y-4">
                            <div class="rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] p-4">
                                <p class="text-xs font-semibold uppercase tracking-wider text-[#146e72]">Filed in this incubatee folder</p>
                                <p class="mt-1 text-sm text-gray-800">The form response is stored here with its uploaded attachment, if provided.</p>
                                <p class="mt-2 text-xs text-gray-700">Review status: <span class="font-semibold" x-text="drawer.status"></span></p>
                            </div>
                            <div x-show="drawer.driveResponseUrl || drawer.driveAttachmentUrl" class="flex flex-wrap gap-2">
                                <a x-show="drawer.driveResponseUrl" x-cloak :href="drawer.driveResponseUrl" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-[#146e72] px-3 py-2 text-xs font-semibold text-white hover:bg-[#10595c]">Open response in Drive</a>
                                <a x-show="drawer.driveAttachmentUrl" x-cloak :href="drawer.driveAttachmentUrl" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-[#1a8a8e]/30 bg-[#eaf6f6] px-3 py-2 text-xs font-semibold text-[#146e72] hover:bg-[#d8eeee]">Open attachment in Drive</a>
                            </div>
                            <dl class="space-y-3 rounded-xl border border-gray-200 p-4">
                                <template x-for="[key,value] in Object.entries(drawer.data || {})" :key="key">
                                    <div class="border-b border-gray-100 pb-2 last:border-0"><dt class="text-xs font-semibold capitalize text-gray-600" x-text="key.replaceAll('_',' ')"></dt><dd class="mt-1 whitespace-pre-wrap break-words text-sm text-gray-900" x-text="typeof value==='object' ? JSON.stringify(value) : value"></dd></div>
                                </template>
                            </dl>
                        </div>
                    </template>
                    <template x-if="drawer.isDriveLink">
                        <div class="space-y-4">
                            <p class="leading-6 text-gray-800">This is a saved Google Drive link, not a synchronized copy. Google Drive applies its own sharing permissions to this folder or document.</p>
                            <a :href="drawer.url" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-xl bg-[#146e72] px-4 py-2 font-semibold text-white hover:bg-[#10595c]">Open Google Drive link</a>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </aside>
</div>

@if (auth()->user()->canManageRecords())
<div x-show="accessModal" x-cloak x-transition.opacity class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="accessModal=false">
    <section role="dialog" aria-modal="true" aria-labelledby="folder-access-title" class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl">
        <form method="POST" :action="accessStartup ? '{{ url('/startup-folders') }}/'+accessStartup.id+'/access' : '#'">
            @csrf
            <input type="hidden" name="restricted" :value="restrictedDraft ? '1' : '0'">
            <header class="flex items-start justify-between gap-4 bg-[#146e72] px-6 py-5 text-white">
                <div><p class="text-xs font-semibold uppercase tracking-wider text-white/80">Folder permissions</p><h2 id="folder-access-title" class="mt-1 text-lg font-bold" x-text="accessStartup?.name"></h2></div>
                <button type="button" @click="accessModal=false" aria-label="Close folder access" class="rounded-lg p-1.5 text-white/90 hover:bg-white/15">✕</button>
            </header>
            <div class="space-y-4 p-6">
                <label class="flex items-start gap-3 rounded-xl border border-gray-200 p-4">
                    <input type="checkbox" :checked="restrictedDraft" @change="restrictedDraft=$event.target.checked" class="mt-0.5 rounded border-gray-300 text-[#146e72] focus:ring-[#1a8a8e]">
                    <span><span class="block text-sm font-semibold text-gray-900">Restrict this folder to selected team members</span><span class="mt-1 block text-xs leading-5 text-gray-700">When enabled, only the folder manager and selected users can see its forms and attachments in Marian TBI Connect.</span></span>
                </label>
                <fieldset x-show="restrictedDraft" x-cloak class="space-y-2">
                    <legend class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-700">Allow access to</legend>
                    <template x-for="user in teamUsers" :key="user.id">
                        <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2.5">
                            <input type="checkbox" name="user_ids[]" :value="user.id" :checked="selectedUsers.includes(String(user.id))" @change="$event.target.checked ? selectedUsers.push(String(user.id)) : selectedUsers.splice(selectedUsers.indexOf(String(user.id)),1)" class="rounded border-gray-300 text-[#146e72] focus:ring-[#1a8a8e]">
                            <span class="min-w-0"><span class="block text-sm font-medium text-gray-900" x-text="user.name"></span><span class="block text-xs text-gray-700" x-text="user.email"></span></span>
                        </label>
                    </template>
                    <p class="text-xs leading-5 text-gray-700">The person who manages this folder always retains access. Users granted access here can read saved forms and attachments.</p>
                </fieldset>
                @if ($driveConnected)
                    <p class="rounded-xl bg-[#eaf6f6] p-3 text-xs leading-5 text-[#14595c]">Saving applies the same access list to Google Drive. Selected users receive read access to the Drive folder; removed users lose the app-managed Drive permission.</p>
                @else
                    <p class="rounded-xl bg-amber-50 p-3 text-xs leading-5 text-amber-900">This setting controls the app only until the dedicated Marian TBI Google Drive account is connected. Drive sharing must be updated separately for now.</p>
                @endif
            </div>
            @endif
            <footer class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4">
                <button type="button" @click="accessModal=false" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-100">Cancel</button>
                <button type="submit" class="rounded-xl bg-[#146e72] px-5 py-2 text-sm font-semibold text-white hover:bg-[#10595c]">Save access</button>
            </footer>
        </form>
    </section>
</div>
@endsection
