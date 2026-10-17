<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Contact Registration · Marian TBI Connect</title>
    <link rel="icon" type="image/png" href="{{ asset('marian-tbi-logo.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>body{font-family:Inter,ui-sans-serif,system-ui,sans-serif}[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-[#F8FAFC] text-[#0F172A] antialiased"
      x-data="{
          stream: '',
          submitted: @js(session()->has('registration_status')),
          error: '',
          form: {
              name: '', email: '', phone: '', affiliationType: '', affiliationOther: '',
              organization: '', role: '', roleOther: '', academicProgram: '', academicYear: '',
              domains: [], domainOther: '', technicalSkills: [], technicalOther: '', skillDetails: ''
          },
          affiliations: {
              internal: ['University department / office', 'Research lab / center', 'Student organization / academic unit', 'Other'],
              external: ['Company / Corporation', 'Startup / Small business', 'Freelancer / Self-employed', 'Government agency', 'Investor / VC fund', 'Nonprofit / NGO', 'Professional firm / Consultancy', 'Other']
          },
          roles: {
              internal: ['University Faculty Advisor', 'Campus Researcher', 'Student Lead', 'Department Chair', 'TTO Officer', 'Other'],
              external: ['Industry Mentor', 'Venture Capitalist', 'Angel Investor', 'Corporate Partner', 'Government Grant Officer', 'Legal/Compliance Advisor', 'Other']
          },
          domainOptions: ['Health / Life Sciences', 'Information Technology', 'Agriculture / Food Systems', 'Education', 'Finance / Fintech', 'Clean Energy / Environment', 'Engineering / Manufacturing', 'Business / Entrepreneurship', 'Legal / Compliance', 'Other'],
          technicalOptions: ['Programming / Software Development', 'Web Development', 'Mobile App Development', 'Data Analytics / Data Science', 'Artificial Intelligence / Machine Learning', 'Cybersecurity', 'Cloud / DevOps', 'UI / UX Design', 'Hardware / Electronics / IoT', 'Research Methods', 'Product / Project Management', 'Other'],
          get isStudent() { return this.stream === 'internal' && this.form.role === 'Student Lead' },
          get isIndependent() { return this.stream === 'external' && this.form.affiliationType === 'Freelancer / Self-employed' },
          setStream(value) {
              this.stream = value;
              this.form.affiliationType = '';
              this.form.affiliationOther = '';
              this.form.organization = '';
              this.form.role = '';
              this.form.roleOther = '';
              this.form.academicProgram = '';
              this.form.academicYear = '';
          },
          submit() {
              this.error = '';
              if (!this.stream) {
                  this.error = 'Select a stream before submitting your application.';
                  return;
              }
              if (!this.form.domains.length) {
                  this.error = 'Select at least one domain expertise area.';
                  return;
              }
              if (this.form.domains.includes('Other') && !this.form.domainOther.trim()) {
                  this.error = 'Specify your other domain expertise.';
                  return;
              }
              if (this.form.technicalSkills.includes('Other') && !this.form.technicalOther.trim()) {
                  this.error = 'Specify your other technical skill.';
                  return;
              }
              this.$refs.registrationForm.submit();
          }
      }">
    <header class="mx-auto flex max-w-3xl items-center gap-3 px-6 pt-8">
        <img src="{{ asset('marian-tbi-logo.png') }}" alt="" class="h-10 w-10 object-contain">
        <p class="font-bold">Marian TBI Connect</p>
    </header>

    <main class="mx-auto max-w-3xl px-6 py-8">
        <section class="rounded-2xl bg-gradient-to-r from-[#b92369] to-[#1a8a8e] p-8 text-white shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wider text-white/70">Join the Network</p>
            <h1 class="mt-1 text-2xl font-bold">Contact Registration</h1>
            <p class="mt-1 text-sm text-white/85">Submit your details to connect with the Marian TBI community. An administrator will review your application.</p>
        </section>

        @if ($errors->any())
            <div role="alert" class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-semibold">Please review the submitted information:</p>
                <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <form x-ref="registrationForm" action="{{ route('contact-registration.store') }}" method="POST" x-show="!submitted" @submit.prevent="submit()" class="mt-6 space-y-6 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            @csrf
            <input type="hidden" name="stream" :value="stream">
            <fieldset>
                <legend class="mb-3 text-sm font-semibold text-slate-700">Choose your stream</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <button type="button" @click="setStream('internal')"
                            class="rounded-2xl border-2 p-4 text-left transition hover:shadow-sm"
                            :class="stream === 'internal' ? 'border-[#b92369] bg-[#fcf0f5]' : 'border-gray-200 bg-white'">
                        <span class="block text-xs font-semibold text-[#b92369]">Internal Academic</span>
                        <span class="mt-1 block text-sm font-medium text-slate-800">University and research community</span>
                    </button>
                    <button type="button" @click="setStream('external')"
                            class="rounded-2xl border-2 p-4 text-left transition hover:shadow-sm"
                            :class="stream === 'external' ? 'border-[#1a8a8e] bg-[#eaf6f6]' : 'border-gray-200 bg-white'">
                        <span class="block text-xs font-semibold text-[#1a8a8e]">External Industry</span>
                        <span class="mt-1 block text-sm font-medium text-slate-800">Industry, investment, and partner network</span>
                    </button>
                </div>
            </fieldset>

            <div x-show="error" x-cloak role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="error"></div>

            <div class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="contact-name" class="mb-1 block text-xs font-semibold text-slate-600">Full name</label>
                        <input id="contact-name" name="name" type="text" autocomplete="name" required x-model="form.name" maxlength="160"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                    </div>
                    <div>
                        <label for="contact-email" class="mb-1 block text-xs font-semibold text-slate-600">Email</label>
                        <input id="contact-email" name="email" type="email" autocomplete="email" required x-model="form.email"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                    </div>
                    <div>
                        <label for="contact-phone" class="mb-1 block text-xs font-semibold text-slate-600">Phone number <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="contact-phone" name="phone" type="tel" autocomplete="tel" x-model="form.phone" maxlength="40" placeholder="e.g. +63 917 123 4567"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                    </div>
                </div>

                <fieldset x-show="stream" x-cloak :disabled="!stream" class="space-y-4">
                    <h2 class="text-sm font-semibold text-slate-800">Affiliation and role</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="contact-affiliation" class="mb-1 block text-xs font-semibold text-slate-600">Affiliation type</label>
                            <select id="contact-affiliation" name="affiliation_type" x-model="form.affiliationType" required
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                                <option value="">Select affiliation type</option>
                                <template x-for="affiliation in affiliations[stream]" :key="affiliation"><option :value="affiliation" x-text="affiliation"></option></template>
                            </select>
                        </div>
                        <div>
                            <label for="contact-role" class="mb-1 block text-xs font-semibold text-slate-600">Role</label>
                            <select id="contact-role" name="role" x-model="form.role" required
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                                <option value="">Select role</option>
                                <template x-for="role in roles[stream]" :key="role"><option :value="role" x-text="role"></option></template>
                            </select>
                        </div>
                        <div x-show="form.affiliationType === 'Other'" x-cloak>
                            <label for="contact-affiliation-other" class="mb-1 block text-xs font-semibold text-slate-600">Specify affiliation type</label>
                            <input id="contact-affiliation-other" name="affiliation_other" x-model="form.affiliationOther" :required="form.affiliationType === 'Other'" :disabled="form.affiliationType !== 'Other'" maxlength="100"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                        </div>
                        <div x-show="!isIndependent" x-cloak>
                            <label for="contact-organization" class="mb-1 block text-xs font-semibold text-slate-600" x-text="stream === 'internal' ? 'University / Department / Office' : 'Organization / Company / Fund'"></label>
                            <input id="contact-organization" name="organization" x-model="form.organization" :required="!isIndependent" :disabled="isIndependent" maxlength="160"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                        </div>
                        <div x-show="isIndependent" x-cloak>
                            <label for="contact-independent-organization" class="mb-1 block text-xs font-semibold text-slate-600">Business / professional name <span class="font-normal text-slate-400">(optional)</span></label>
                            <input id="contact-independent-organization" name="organization" x-model="form.organization" :disabled="!isIndependent" maxlength="160" placeholder="Leave blank if you work under your own name"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                        </div>
                        <div x-show="form.role === 'Other'" x-cloak>
                            <label for="contact-role-other" class="mb-1 block text-xs font-semibold text-slate-600">Specify role</label>
                            <input id="contact-role-other" name="role_other" x-model="form.roleOther" :required="form.role === 'Other'" :disabled="form.role !== 'Other'" maxlength="120"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                        </div>
                    </div>
                    <div x-show="isStudent" x-cloak class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="contact-academic-program" class="mb-1 block text-xs font-semibold text-slate-600">Program / Course</label>
                            <input id="contact-academic-program" name="academic_program" x-model="form.academicProgram" :required="isStudent" :disabled="!isStudent" maxlength="120" placeholder="e.g. BS Computer Science"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                        </div>
                        <div>
                            <label for="contact-academic-year" class="mb-1 block text-xs font-semibold text-slate-600">Year level</label>
                            <select id="contact-academic-year" name="academic_year" x-model="form.academicYear" :required="isStudent" :disabled="!isStudent"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                                <option value="">Select year</option><option>1st year</option><option>2nd year</option><option>3rd year</option><option>4th year</option><option>5th year or higher</option><option>Graduate student</option>
                            </select>
                        </div>
                    </div>
                </section>

                <fieldset class="space-y-3">
                    <legend class="text-xs font-semibold text-slate-600">Domain expertise <span class="font-normal text-slate-400">(select all that apply)</span></legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <template x-for="domain in domainOptions" :key="domain">
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-700 hover:border-[#1a8a8e]/40 hover:bg-[#eaf6f6]/50">
                                <input type="checkbox" name="domains[]" :value="domain" x-model="form.domains" class="rounded border-slate-300 text-[#1a8a8e] focus:ring-[#1a8a8e]">
                                <span x-text="domain"></span>
                            </label>
                        </template>
                    </div>
                    <div x-show="form.domains.includes('Other')" x-cloak>
                        <label for="contact-domain-other" class="mb-1 block text-xs font-semibold text-slate-600">Specify domain</label>
                        <input id="contact-domain-other" name="domain_other" x-model="form.domainOther" :required="form.domains.includes('Other')" :disabled="!form.domains.includes('Other')" maxlength="120"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                    </div>
                </fieldset>

                <fieldset class="space-y-3">
                    <legend class="text-xs font-semibold text-slate-600">Technical skills <span class="font-normal text-slate-400">(optional — choose specific skills)</span></legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <template x-for="skill in technicalOptions" :key="skill">
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-700 hover:border-[#b92369]/40 hover:bg-[#fcf0f5]/50">
                                <input type="checkbox" name="technical_skills[]" :value="skill" x-model="form.technicalSkills" class="rounded border-slate-300 text-[#b92369] focus:ring-[#b92369]">
                                <span x-text="skill"></span>
                            </label>
                        </template>
                    </div>
                    <div x-show="form.technicalSkills.includes('Other')" x-cloak>
                        <label for="contact-technical-other" class="mb-1 block text-xs font-semibold text-slate-600">Specify technical skill</label>
                        <input id="contact-technical-other" name="technical_other" x-model="form.technicalOther" :required="form.technicalSkills.includes('Other')" :disabled="!form.technicalSkills.includes('Other')" maxlength="160"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                    </div>
                    <div>
                        <label for="contact-skill-details" class="mb-1 block text-xs font-semibold text-slate-600">Tools, languages or methods <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="contact-skill-details" name="skill_details" x-model="form.skillDetails" maxlength="200" placeholder="e.g. Python, JavaScript, React, Laravel, SQL"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                    </div>
                </fieldset>
            </div>

            <button type="submit" class="w-full rounded-xl bg-[#1a8a8e] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#146e72]">
                Submit Registration
            </button>
            <p class="text-center text-xs text-gray-500">Your application will be reviewed by a Super Admin before account access is granted.</p>
        </form>

        <section x-show="submitted" x-cloak class="mt-6 rounded-2xl border border-gray-100 bg-white p-10 text-center shadow-sm" role="status" aria-live="polite">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-2xl text-white" aria-hidden="true">✓</div>
            <h2 class="mt-4 text-lg font-bold">Registration submitted</h2>
            <p class="mt-1 text-sm text-gray-500">{{ session('registration_status', 'Your application is ready for administrator review.') }}</p>
        </fieldset>
    </main>
</body>
</html>
