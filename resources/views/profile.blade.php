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
@if ($errors->any())
    <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <p class="font-semibold">Your profile could not be updated.</p>
        <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
<section class="max-w-2xl rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div><h2 class="text-base font-bold text-slate-900">Account credentials</h2><p class="mt-1 text-sm text-gray-500">Your current role is {{ $user->roleLabel() }}.</p></div>
        <span class="rounded-full bg-[#eaf6f6] px-3 py-1 text-xs font-semibold text-[#146e72]">{{ $user->roleLabel() }}</span>
    </div>
    <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
        @csrf
        @method('PUT')
        <div>
            <label for="profile-name" class="mb-1.5 block text-sm font-semibold text-slate-700">Name</label>
            <input id="profile-name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name" class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
        </div>
        <div>
            <label for="profile-email" class="mb-1.5 block text-sm font-semibold text-slate-700">Sign-in email</label>
            <input id="profile-email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email" class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
        </div>
        <div class="border-t border-gray-100 pt-5">
            <h3 class="text-sm font-bold text-slate-900">Change password <span class="font-normal text-gray-500">({{ session('force_password_change') ? 'required to finish setup' : 'optional' }})</span></h3>
            @if (session('force_password_change'))
                <p class="mt-1 text-sm text-gray-500">Choose a new password for your account. Your current password is not needed during setup.</p>
            @endif
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="profile-new-password" class="mb-1.5 block text-sm font-semibold text-slate-700">New password</label>
                    <input id="profile-new-password" name="new_password" type="password" minlength="8" autocomplete="new-password" @required(session('force_password_change')) class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                </div>
                <div>
                    <label for="profile-new-password-confirmation" class="mb-1.5 block text-sm font-semibold text-slate-700">Confirm new password</label>
                    <input id="profile-new-password-confirmation" name="new_password_confirmation" type="password" minlength="8" autocomplete="new-password" class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                </div>
            </div>
        </div>
        <div>
            <label for="profile-current-password" class="mb-1.5 block text-sm font-semibold text-slate-700">Current password <span class="font-normal text-gray-500">({{ session('force_password_change') ? 'not required during setup' : 'required to save' }})</span></label>
            <input id="profile-current-password" name="current_password" type="password" @required(!session('force_password_change')) autocomplete="current-password" class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
        </div>
        <div class="flex justify-end">
            <button type="submit" class="rounded-xl bg-[#1a8a8e] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#146e72]">Save profile</button>
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
