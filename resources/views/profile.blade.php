@extends('layouts.app')
@section('title','My Profile')
@section('eyebrow','Account Settings')
@section('heading','My Profile')
@section('subheading','Update your name, sign-in email, and password.')

@section('content')
@if (session('profile_status'))
    <div role="status" class="mb-4 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('profile_status') }}</div>
@endif
@if (session('success'))
    <div role="status" class="mb-4 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('success') }}</div>
@endif
<section class="max-w-2xl rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
    <div class="mb-6">
        <h2 class="text-base font-bold text-slate-900">Account credentials</h2>
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
    <form method="POST" action="{{ route('profile.update') }}" class="space-y-5"
          x-data="{
              affiliation: @js($affiliationValue),
              role: @js($roleValue),
              domains: @js($selectedDomains),
              skills: @js($selectedSkills),
              tools: @js($selectedTools)
          }">
        @csrf
        @method('PUT')
        <div>
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
        <div>
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
        <div class="flex justify-end">
            <button type="submit" class="rounded-xl bg-[#1a8a8e] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#146e72]">Save profile</button>
        </div>
    </form>
</section>

<section class="mt-6 max-w-2xl rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
    <div class="mb-6">
        <h2 class="text-base font-bold text-slate-900">Change password <span class="font-normal text-gray-500">({{ session('force_password_change') ? 'required to finish setup' : 'optional' }})</span></h2>
        @if (session('force_password_change'))
            <p class="mt-1 text-sm text-gray-500">Choose a new password for your account. Your current password is not needed during setup.</p>
        @endif
    </div>
    <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-5">
        @csrf
        @method('PUT')
        <div>
            <label for="profile-current-password" class="mb-1.5 block text-sm font-semibold text-slate-700">Current password <span class="font-normal text-gray-500">({{ session('force_password_change') ? 'not required during setup' : 'required to save' }})</span></label>
            <input id="profile-current-password" name="current_password" type="password" @required(!session('force_password_change')) autocomplete="current-password" class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
            @error('current_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
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
        <div class="flex justify-end">
            <button type="submit" class="rounded-xl bg-[#1a8a8e] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#146e72]">Update password</button>
        </div>
    </form>
</section>

<section class="mt-6 max-w-2xl rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
    <div class="mb-4 flex items-center justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-slate-900">Google Workspace</h2>
            <p class="mt-1 text-sm text-gray-500">Connect your Google account for single sign-on and Drive access.</p>
        </div>
        @if ($user->google_access_token || $user->google_refresh_token || $user->google_id)
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Connected as {{ $user->google_email ?: $user->email }}</span>
        @else
            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">Not Connected - Using Central Marian TBI System Account</span>
        @endif
    </div>
    @if ($user->google_access_token || $user->google_refresh_token || $user->google_id)
        <form method="POST" action="{{ route('auth.google.disconnect') }}" class="mt-4">
            @csrf
            <button type="submit" class="min-h-11 rounded-xl border border-gray-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]">Disconnect</button>
        </form>
    @else
        <a href="{{ route('auth.google') }}" class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-[#1a8a8e] px-5 py-3 text-sm font-semibold text-white hover:bg-[#146e72] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]">Connect Google Account</a>
    @endif
</section>
@endsection
