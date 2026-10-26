@extends('layouts.app')
@section('title','Contact Intake')
@section('eyebrow','Network Administration')
@section('heading','Contact Registration Queue')
@section('subheading','Review public contact applications and verify new network members.')

@section('banner-actions')
    @if (auth()->user()->canManageRecords())
        <button type="button" @click="$dispatch('copy-contact-registration-link')"
                class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-[#1a8a8e] shadow-sm transition hover:bg-[#eaf6f6]">
            Copy Share Link
        </button>
    @endif
@endsection

@section('content')
<div
    x-data="{
        applications: @js($applications),
        previewApplication: null,
        shareUrl: @js(url('/contact-registration')),
        notice: '',
        noticeType: 'success',
        async copyShareLink() {
            try {
                await navigator.clipboard.writeText(this.shareUrl);
                this.notice = 'Registration link copied to clipboard.';
                this.noticeType = 'success';
            } catch (error) {
                this.notice = 'Could not copy the link. Copy it manually: ' + this.shareUrl;
                this.noticeType = 'error';
            }
        },
        preview(application) { this.previewApplication = application; }
    }"
    @copy-contact-registration-link.window="copyShareLink()"
    class="space-y-4"
>
    @if (session('intake_status'))
        <div role="status" class="rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('intake_status') }}</div>
    @endif
    <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        Verification reviews the application only. It does not create a login or send an email invitation.
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500"><span class="font-semibold text-slate-800" x-text="applications.filter(application => application.status === 'Pending').length"></span> applications awaiting review</p>
    </div>

    <div x-show="notice" x-cloak x-transition role="status" aria-live="polite"
         class="rounded-xl border px-4 py-3 text-sm"
         :class="noticeType === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-800'">
        <span x-text="notice"></span>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-100 bg-white shadow-sm">
        <table class="min-w-[1350px] w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th scope="col" class="px-5 py-3">Applicant & Contact</th>
                    <th scope="col" class="px-4 py-3">Stream</th>
                    <th scope="col" class="px-4 py-3">Affiliation</th>
                    <th scope="col" class="px-4 py-3">Role / Academic Details</th>
                    <th scope="col" class="px-4 py-3">Domain Expertise</th>
                    <th scope="col" class="px-4 py-3">Technical Skills</th>
                    <th scope="col" class="px-4 py-3">Submitted</th>
                    <th scope="col" class="px-4 py-3">Status</th>
                    <th scope="col" class="px-5 py-3">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-for="application in applications" :key="application.id">
                    <tr class="align-top hover:bg-gray-50/70">
                        <th scope="row" class="px-5 py-4 font-semibold text-slate-800">
                            <span class="block" x-text="application.name"></span>
                            <span class="mt-1 block text-xs font-normal text-gray-500" x-text="application.email"></span>
                            <a x-show="application.phone" :href="'tel:' + application.phone" class="mt-1 block text-xs font-normal text-[#1a8a8e] hover:underline" x-text="application.phone"></a>
                        </th>
                        <td class="px-4 py-4 text-gray-700" x-text="application.stream"></td>
                        <td class="px-4 py-4 text-gray-700">
                            <span class="block font-medium" x-text="application.affiliationType"></span>
                            <span class="mt-1 block text-xs text-gray-500" x-text="application.organization || 'Independent professional'"></span>
                        </td>
                        <td class="px-4 py-4 text-gray-700">
                            <span class="block font-medium" x-text="application.role"></span>
                            <span x-show="application.academicProgram" class="mt-1 block text-xs text-gray-500" x-text="application.academicProgram + (application.academicYear ? ' · ' + application.academicYear : '')"></span>
                        </td>
                        <td class="px-4 py-4 text-gray-700">
                            <div class="flex max-w-xs flex-wrap gap-1">
                                <template x-for="domain in application.domains" :key="domain"><span class="rounded-md bg-[#eaf6f6] px-2 py-1 text-xs text-[#1a686b]" x-text="domain"></span></template>
                                <span x-show="application.domainOther" class="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700" x-text="application.domainOther"></span>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-gray-700">
                            <div class="flex max-w-xs flex-wrap gap-1">
                                <template x-for="skill in application.technicalSkills" :key="skill"><span class="rounded-md bg-[#fcf0f5] px-2 py-1 text-xs text-[#8e1c51]" x-text="skill"></span></template>
                                <span x-show="application.technicalOther" class="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700" x-text="application.technicalOther"></span>
                            </div>
                            <span x-show="application.skillDetails" class="mt-1 block text-xs text-gray-500" x-text="application.skillDetails"></span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-gray-600" x-text="application.submitted"></td>
                        <td class="px-4 py-4">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                  :class="application.status === 'Pending' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'"
                                  x-text="application.status"></span>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex flex-col items-start gap-2">
                                <button type="button" @click="preview(application)" class="whitespace-nowrap rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Preview application</button>
                                @if (auth()->user()->canManageRecords())
                                    <form x-show="application.status === 'Pending'" method="POST" :action="'{{ url('/contact-intake') }}/'+application.id+'/verify'">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="whitespace-nowrap rounded-lg bg-[#1a8a8e] px-3 py-2 text-xs font-semibold text-white transition hover:bg-[#146e72]">Verify &amp; Add to Contacts</button>
                                    </form>
                                    <form x-show="application.status === 'Pending'" method="POST" :action="'{{ url('/contact-intake') }}/'+application.id+'/provision'">
                                        @csrf
                                        <button type="submit" class="whitespace-nowrap rounded-lg bg-[#146e72] px-3 py-2 text-xs font-semibold text-white transition hover:bg-[#105a5d]">Approve &amp; Invite Account</button>
                                    </form>
                                    <form method="POST" :action="'{{ url('/contact-intake') }}/'+application.id"
                                          @submit="if (!confirm('Permanently delete this registration application?')) $event.preventDefault()">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="whitespace-nowrap rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-50">Delete application</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                </template>
                <tr x-show="applications.length === 0">
                    <td colspan="9" class="px-5 py-12 text-center text-sm text-gray-500">There are no contact applications to review.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div x-show="previewApplication" x-cloak x-transition.opacity
         @click.self="previewApplication=null" @keydown.escape.window="previewApplication=null"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
        <section x-show="previewApplication" role="dialog" aria-modal="true" aria-labelledby="contact-preview-title"
                 class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <template x-if="previewApplication">
                <div>
                    <div class="sticky top-0 flex items-start justify-between gap-4 border-b border-gray-100 bg-white px-6 py-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-[#1a8a8e]">Registration submission</p>
                            <h2 id="contact-preview-title" class="mt-1 text-xl font-bold text-slate-900" x-text="previewApplication.name"></h2>
                        </div>
                        <button type="button" @click="previewApplication=null" aria-label="Close application preview" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100">✕</button>
                    </div>
                    <div class="grid gap-x-8 gap-y-5 px-6 py-5 sm:grid-cols-2">
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Email</p><p class="mt-1 break-words text-sm text-slate-800" x-text="previewApplication.email || 'Not provided'"></p></div>
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Phone</p><p class="mt-1 text-sm text-slate-800" x-text="previewApplication.phone || 'Not provided'"></p></div>
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Stream</p><p class="mt-1 text-sm text-slate-800" x-text="previewApplication.stream || 'Not provided'"></p></div>
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Affiliation type</p><p class="mt-1 text-sm text-slate-800" x-text="previewApplication.affiliationOther || previewApplication.affiliationType || 'Not provided'"></p></div>
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Department / organization</p><p class="mt-1 text-sm text-slate-800" x-text="previewApplication.organization || 'Independent professional'"></p></div>
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Role</p><p class="mt-1 text-sm text-slate-800" x-text="previewApplication.roleOther || previewApplication.role || 'Not provided'"></p></div>
                        <div x-show="previewApplication.academicProgram || previewApplication.academicYear">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Academic details</p>
                            <p class="mt-1 text-sm text-slate-800" x-text="[previewApplication.academicProgram, previewApplication.academicYear].filter(Boolean).join(' · ')"></p>
                        </div>
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Domain expertise</p>
                            <p class="mt-1 text-sm text-slate-800" x-text="[...(previewApplication.domains || []), previewApplication.domainOther].filter(Boolean).join(', ') || 'Not provided'"></p>
                        </div>
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Technical skills</p>
                            <p class="mt-1 text-sm text-slate-800" x-text="[...(previewApplication.technicalSkills || []), previewApplication.technicalOther].filter(Boolean).join(', ') || 'Not provided'"></p>
                        </div>
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Tools, languages or methods</p><p class="mt-1 text-sm text-slate-800" x-text="previewApplication.skillDetails || 'Not provided'"></p></div>
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Submitted</p><p class="mt-1 text-sm text-slate-800" x-text="previewApplication.submitted || 'Not provided'"></p></div>
                    </div>
                    <div class="flex justify-end border-t border-gray-100 bg-gray-50 px-6 py-4">
                        <button type="button" @click="previewApplication=null" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Close preview</button>
                    </div>
                </div>
            </template>
        </section>
    </div>
</div>
@endsection
