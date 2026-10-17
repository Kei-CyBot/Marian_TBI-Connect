@extends('layouts.app')
@section('title','My Profile')
@section('eyebrow','Account Settings')
@section('heading','My Profile')
@section('subheading','Update your name, sign-in email, and password.')

@section('content')
@if (session('profile_status'))
    <div role="status" class="mb-4 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm font-medium text-[#146e72]">{{ session('profile_status') }}</div>
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
            <h3 class="text-sm font-bold text-slate-900">Change password <span class="font-normal text-gray-500">(optional)</span></h3>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="profile-new-password" class="mb-1.5 block text-sm font-semibold text-slate-700">New password</label>
                    <input id="profile-new-password" name="new_password" type="password" minlength="8" autocomplete="new-password" class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                </div>
                <div>
                    <label for="profile-new-password-confirmation" class="mb-1.5 block text-sm font-semibold text-slate-700">Confirm new password</label>
                    <input id="profile-new-password-confirmation" name="new_password_confirmation" type="password" minlength="8" autocomplete="new-password" class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                </div>
            </div>
        </div>
        <div>
            <label for="profile-current-password" class="mb-1.5 block text-sm font-semibold text-slate-700">Current password <span class="font-normal text-gray-500">(required to save)</span></label>
            <input id="profile-current-password" name="current_password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
        </div>
        <div class="flex justify-end">
            <button type="submit" class="rounded-xl bg-[#1a8a8e] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#146e72]">Save profile</button>
        </div>
    </form>
</section>
@endsection
