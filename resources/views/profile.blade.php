@extends('layouts.app')
@section('title','My Profile')
@section('eyebrow','Account Settings')
@section('heading','My Profile')
@section('subheading','Update your name, sign-in email, and password.')

@push('head')
    <style>main > header:first-of-type { display: none; }</style>
@endpush

@section('content')
@php
    $initials = collect(explode(' ', trim((string) $user->name)))
        ->filter()
        ->take(2)
        ->map(static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    $googleLinked = filled($user->google_id);
    $memberSince = $user->created_at?->format('F j, Y') ?? '—';
@endphp

@if (session('profile_status'))
    <div role="status" class="mb-4 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('profile_status') }}</div>
@endif
@if (session('success'))
    <div role="status" class="mb-4 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('success') }}</div>
@endif

<div class="mx-auto max-w-6xl">
    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
        <div class="col-span-full relative overflow-hidden rounded-2xl border border-gray-100 bg-white px-6 py-6 shadow-sm sm:px-8 sm:py-7">
            <span class="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-rose-800/75 via-rose-700/45 to-teal-700/75" aria-hidden="true"></span>
            <p class="inline-flex items-center gap-1.5 rounded-md bg-teal-50/80 px-2 py-0.5 text-[11px] font-semibold tracking-wide text-teal-800 ring-1 ring-inset ring-teal-200/70">Account Settings</p>
            <h1 class="mt-2 text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">My Profile</h1>
            <p class="mt-1 text-sm leading-relaxed text-slate-500">Update your name, sign-in email, and password.</p>
        </div>

        <aside class="lg:col-span-4">
            <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-7 lg:sticky lg:top-6">
                <div class="flex flex-col items-center text-center">
                    <div class="relative">
                        @if ($user->avatar)
                            <img src="{{ $user->avatar }}" alt="{{ $user->name }}" class="h-20 w-20 rounded-full object-cover ring-4 ring-slate-50">
                        @else
                            <div class="flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-[#1a8a8e] to-[#146e72] text-2xl font-semibold text-white ring-4 ring-slate-50">
                                {{ $initials ?: 'U' }}
                            </div>
                        @endif
                        <span class="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-emerald-500 text-white" title="Active account">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/>
                            </svg>
                        </span>
                    </div>
                    <h2 class="mt-4 text-lg font-semibold tracking-tight text-slate-900">{{ $user->name }}</h2>
                    <p class="mt-1 break-all text-sm text-slate-500">{{ $user->email }}</p>
                </div>

                <dl class="mt-6 divide-y divide-slate-100 border-t border-slate-100">
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Account Status</dt>
                        <dd>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-inset ring-emerald-200/70">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                Active
                            </span>
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Google Sign-In</dt>
                        <dd>
                            @if ($googleLinked)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-800 ring-1 ring-inset ring-teal-200/70">
                                    <span class="h-1.5 w-1.5 rounded-full bg-teal-600"></span>
                                    Connected
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-inset ring-slate-200/80">
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                    Not linked
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Member Since</dt>
                        <dd class="text-sm font-medium text-slate-700">{{ $memberSince }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Classification</dt>
                        <dd>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-200/80">
                                <span class="h-1.5 w-1.5 rounded-full {{ $classification === 'internal' ? 'bg-teal-600' : 'bg-rose-500' }}"></span>
                                {{ $classification === 'internal' ? 'Internal' : 'External' }}
                            </span>
                        </dd>
                    </div>
                </dl>

                <div class="mt-6 border-t border-slate-100 pt-5">
                    @if ($googleLinked)
                        <p class="text-xs leading-5 text-slate-500">
                            Connected as <span class="font-medium text-slate-700">{{ $user->google_email ?: $user->email }}</span>.
                        </p>
                        <form method="POST" action="{{ route('auth.google.disconnect') }}" class="mt-3">
                            @csrf
                            <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]">
                                Disconnect Google Account
                            </button>
                        </form>
                    @else
                        <p class="text-xs leading-5 text-slate-500">Link your Google account for single sign-on and Drive access.</p>
                        <a href="{{ route('auth.google') }}" class="mt-3 inline-flex min-h-10 w-full items-center justify-center rounded-xl bg-[#1a8a8e] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#146e72] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]">
                            Connect Google Account
                        </a>
                    @endif
                </div>
            </section>
        </aside>

        <div class="space-y-6 lg:col-span-8">
            <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-5 sm:px-8">
                    <h2 class="text-base font-bold text-slate-900">Account credentials</h2>
                    <p class="mt-1 text-sm text-slate-500">Manage the identity and contact details connected to your account.</p>
                </div>

                @php
                    $affiliationValue = (string) old('affiliation', $contact?->affiliation_type);
                    $affiliationOther = (string) old('affiliation_other', '');
                    if ($affiliationValue !== '' && ! in_array($affiliationValue, $affiliations, true)) {
                        $affiliationOther = $affiliationOther ?: $affiliationValue;
                        $affiliationValue = 'Other';
                    }

                    $roleValue = (string) old('role', $contact?->role);
                    $roleOther = (string) old('role_other', '');
                    if ($roleValue !== '' && ! in_array($roleValue, $roles, true)) {
                        $roleOther = $roleOther ?: $roleValue;
                        $roleValue = 'Other';
                    }

                    $storedDomains = (array) old('domain_expertise', $contact?->domains ?? []);
                    $selectedDomains = array_values(array_intersect($storedDomains, $domainExpertiseOptions));
                    $domainExtras = array_values(array_diff($storedDomains, $domainExpertiseOptions));
                    $domainOther = (string) old('domain_expertise_other', implode(', ', $domainExtras));
                    if ($domainExtras !== []) {
                        $selectedDomains[] = 'Other';
                    }

                    $storedSkills = (array) old('technical_skills', $contact?->technical_skills ?? []);
                    $selectedSkills = array_values(array_intersect($storedSkills, $technicalSkillsOptions));
                    $skillExtras = array_values(array_diff($storedSkills, $technicalSkillsOptions));
                    $skillsOther = (string) old('technical_skills_other', implode(', ', $skillExtras));
                    if ($skillExtras !== []) {
                        $selectedSkills[] = 'Other';
                    }

                    $storedTools = (array) old('tools_languages_methods', array_filter(array_map('trim', explode(',', (string) ($contact?->skill_details ?? '')))));
                    $selectedTools = array_values(array_intersect($storedTools, $toolsOptions));
                    $toolExtras = array_values(array_diff($storedTools, $toolsOptions));
                    $toolsOther = (string) old('tools_languages_methods_other', implode(', ', $toolExtras));
                    if ($toolExtras !== []) {
                        $selectedTools[] = 'Other';
                    }
                @endphp

                <form method="POST" action="{{ route('profile.update') }}"
                      x-data="{
                          affiliation: @js($affiliationValue),
                          role: @js($roleValue),
                          domains: @js($selectedDomains),
                          skills: @js($selectedSkills),
                          tools: @js($selectedTools)
                      }">
                    @csrf
                    @method('PUT')

                    <div class="space-y-6 px-6 py-6 sm:px-8">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="profile-name" class="mb-1.5 block text-sm font-semibold text-slate-700">Name</label>
                                <input id="profile-name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name" class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="profile-email" class="mb-1.5 block text-sm font-semibold text-slate-700">Sign-in email</label>
                                <input id="profile-email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email" class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="profile-phone" class="mb-1.5 block text-sm font-semibold text-slate-700">Phone number</label>
                                <input id="profile-phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" maxlength="20" autocomplete="tel" class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                                @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-6">
                            <h3 class="text-sm font-bold text-slate-900">Professional details</h3>
                            <p class="mt-1 text-xs text-slate-500">Optional information used across the directory and ecosystem reports.</p>

                            <div class="mt-5 space-y-5">
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                    <div>
                                        <label for="profile-affiliation" class="mb-1.5 block text-sm font-semibold text-slate-700">Affiliation type</label>
                                        <select id="profile-affiliation" name="affiliation" x-model="affiliation" class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                                            <option value="">— Select affiliation —</option>
                                            @foreach ($affiliations as $option)
                                                <option value="{{ $option }}" @selected($affiliationValue === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                        <input x-show="affiliation === 'Other'" x-cloak type="text" name="affiliation_other" value="{{ $affiliationOther }}" placeholder="Specify other affiliation..." class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                                        @error('affiliation')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="profile-organization" class="mb-1.5 block text-sm font-semibold text-slate-700">Organization / company / fund</label>
                                        <input id="profile-organization" name="organization" value="{{ old('organization', $contact?->organization) }}" maxlength="255" class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                                        @error('organization')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="profile-role" class="mb-1.5 block text-sm font-semibold text-slate-700">Role</label>
                                        <select id="profile-role" name="role" x-model="role" class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                                            <option value="">— Select role —</option>
                                            @foreach ($roles as $option)
                                                <option value="{{ $option }}" @selected($roleValue === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                        <input x-show="role === 'Other'" x-cloak type="text" name="role_other" value="{{ $roleOther }}" placeholder="Specify other role..." class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                                        @error('role')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <div>
                                    <p class="mb-1.5 block text-sm font-semibold text-slate-700">Domain expertise</p>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        @foreach ($domainExpertiseOptions as $option)
                                            <label class="flex items-start gap-2 text-sm text-slate-700">
                                                <input type="checkbox" name="domain_expertise[]" value="{{ $option }}" x-model="domains" @checked(in_array($option, $selectedDomains, true)) class="mt-0.5 rounded border-gray-300 text-[#1a8a8e] focus:ring-[#1a8a8e]">
                                                <span>{{ $option }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <input x-show="domains.includes('Other')" x-cloak type="text" name="domain_expertise_other" value="{{ $domainOther }}" placeholder="Specify other domain expertise..." class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                                    @error('domain_expertise')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <p class="mb-1.5 block text-sm font-semibold text-slate-700">Technical skills</p>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        @foreach ($technicalSkillsOptions as $option)
                                            <label class="flex items-start gap-2 text-sm text-slate-700">
                                                <input type="checkbox" name="technical_skills[]" value="{{ $option }}" x-model="skills" @checked(in_array($option, $selectedSkills, true)) class="mt-0.5 rounded border-gray-300 text-[#1a8a8e] focus:ring-[#1a8a8e]">
                                                <span>{{ $option }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <input x-show="skills.includes('Other')" x-cloak type="text" name="technical_skills_other" value="{{ $skillsOther }}" placeholder="Specify other technical skill..." class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                                    @error('technical_skills')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <p class="mb-1.5 block text-sm font-semibold text-slate-700">Tools, languages, or methods</p>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        @foreach ($toolsOptions as $option)
                                            <label class="flex items-start gap-2 text-sm text-slate-700">
                                                <input type="checkbox" name="tools_languages_methods[]" value="{{ $option }}" x-model="tools" @checked(in_array($option, $selectedTools, true)) class="mt-0.5 rounded border-gray-300 text-[#1a8a8e] focus:ring-[#1a8a8e]">
                                                <span>{{ $option }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <input x-show="tools.includes('Other')" x-cloak type="text" name="tools_languages_methods_other" value="{{ $toolsOther }}" placeholder="Specify other tools, languages, or methods..." class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                                    @error('tools_languages_methods')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-slate-100 bg-slate-50 px-6 py-4 sm:px-8">
                        <button type="submit" class="rounded-xl bg-[#1a8a8e] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#146e72] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e] focus:ring-offset-2">
                            Save Changes
                        </button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-5 sm:px-8">
                    <h2 class="text-base font-bold text-slate-900">Password &amp; security</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        @if (session('force_password_change'))
                            Choose a new password to finish setting up your account. Your current password is not required during setup.
                        @else
                            Use a strong password you do not reuse on other services.
                        @endif
                    </p>
                </div>

                <form method="POST" action="{{ route('profile.password.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 gap-5 px-6 py-6 sm:grid-cols-2 sm:px-8">
                        <div class="sm:col-span-2">
                            <label for="profile-current-password" class="mb-1.5 block text-sm font-semibold text-slate-700">Current password <span class="font-normal text-gray-500">({{ session('force_password_change') ? 'not required during setup' : 'required to save' }})</span></label>
                            <input id="profile-current-password" name="current_password" type="password" @required(!session('force_password_change')) autocomplete="current-password" class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                            @error('current_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="profile-new-password" class="mb-1.5 block text-sm font-semibold text-slate-700">New password</label>
                            <input id="profile-new-password" name="new_password" type="password" minlength="8" autocomplete="new-password" @required(session('force_password_change')) class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                            @error('new_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="profile-new-password-confirmation" class="mb-1.5 block text-sm font-semibold text-slate-700">Confirm new password</label>
                            <input id="profile-new-password-confirmation" name="new_password_confirmation" type="password" minlength="8" autocomplete="new-password" class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-slate-100 bg-slate-50 px-6 py-4 sm:px-8">
                        <button type="submit" class="rounded-xl bg-[#1a8a8e] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#146e72] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e] focus:ring-offset-2">
                            Update Password
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</div>
@endsection
