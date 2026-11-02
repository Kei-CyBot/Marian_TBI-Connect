@extends('layouts.app')
@section('title','Google Integration Settings')
@section('eyebrow','System Integration')
@section('heading','Google Workspace Settings')
@section('subheading','Manage the dedicated Google account and target Drive folder used by Marian TBI Connect.')

@section('content')
<div class="grid gap-5 lg:grid-cols-2">
    <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-slate-900">Google Workspace Connection</h2>
                <p class="mt-1 text-sm text-gray-500">The dedicated account used for Drive folders and Gmail actions.</p>
            </div>
            <span role="status" class="rounded-full px-3 py-1 text-xs font-semibold {{ $connected ? 'bg-[#eaf6f6] text-[#146e72]' : 'bg-amber-50 text-amber-700' }}">
                {{ $connected ? 'Connected' : 'Action Required' }}
            </span>
        </div>

        <dl class="mt-5 divide-y divide-gray-100 text-sm">
            <div class="flex items-center justify-between gap-4 py-2.5">
                <dt class="font-medium text-gray-500">Workspace account</dt>
                <dd class="text-right font-semibold text-slate-900">{{ $accountEmail ?: 'Not configured' }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-2.5">
                <dt class="font-medium text-gray-500">OAuth token</dt>
                <dd class="text-right font-semibold {{ $connected ? 'text-[#146e72]' : 'text-amber-700' }}">{{ $connected ? 'Connected' : 'Action Required' }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-2.5">
                <dt class="font-medium text-gray-500">Token expires</dt>
                <dd class="text-right text-slate-900">{{ $tokenExpiresAt?->format('M j, Y g:i A') ?? 'No stored token' }}</dd>
            </div>
        </dl>

        @if (! $connected)
            <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-sm text-amber-800">
                Reconnect the workspace account to restore Drive and Gmail access.
            </p>
        @endif

        <div class="mt-5 flex flex-wrap gap-2">
            <a href="{{ route('google-drive.connect') }}" class="rounded-xl bg-[#1a8a8e] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#146e72]">
                {{ $connected ? 'Reconnect Google Workspace' : 'Connect Google Workspace' }}
            </a>
            @if ($connection)
                <form method="POST" action="{{ route('admin.settings.google.disconnect') }}" onsubmit="return confirm('Disconnect the Google Workspace account? Drive and Gmail actions will stop until it is reconnected.')">
                    @csrf
                    <button type="submit" class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Disconnect Account</button>
                </form>
            @endif
        </div>
    </section>

    <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
        <h2 class="text-base font-bold text-slate-900">Drive Folder Settings</h2>
        <p class="mt-1 text-sm text-gray-500">Forms and documents are synced into this Google Drive folder.</p>

        <form method="POST" action="{{ route('admin.settings.google.update-folder') }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <label for="folder_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Google Drive folder ID</label>
                <input id="folder_id" name="folder_id" value="{{ old('folder_id', $folderId) }}" required maxlength="255" placeholder="1AbCdEfGhIjKlMnOpQrStUvWxYz" class="w-full rounded-xl border px-3.5 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15 {{ $errors->has('folder_id') ? 'border-red-300 focus:border-red-400' : 'border-gray-200 focus:border-[#1a8a8e]' }}">
                @error('folder_id')
                    <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-2 text-xs text-gray-500">Copy the ID from the folder URL: <span class="font-mono">drive.google.com/drive/folders/&lt;FOLDER_ID&gt;</span>.</p>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-xl bg-[#1a8a8e] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#146e72]">Save folder</button>
            </div>
        </form>
    </section>
</div>
@endsection
