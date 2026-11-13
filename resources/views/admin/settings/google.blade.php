@extends('layouts.app')
@section('title','Google Integration Settings')
@section('eyebrow','System Integration')
@section('heading','Google Workspace Settings')
@section('subheading','Manage the dedicated Google account and target Drive folder used by Marian TBI Connect.')

@section('content')
<div class="grid gap-5 lg:grid-cols-2">
    <section class="ui-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Google Workspace Connection</h2>
                <p class="mt-1 max-w-prose text-sm leading-relaxed text-slate-500">The dedicated account used for Drive folders and Gmail actions.</p>
            </div>
            <span role="status" class="ui-badge {{ $connected ? 'bg-[#eaf6f6] text-[#146e72] ring-[#1a8a8e]/20' : 'bg-amber-50 text-amber-700 ring-amber-200' }}">
                <span class="ui-badge-dot"></span>
                {{ $connected ? 'Connected' : 'Action Required' }}
            </span>
        </div>

        <dl class="mt-5 divide-y divide-slate-100 text-sm">
            <div class="flex items-center justify-between gap-4 py-2.5">
                <dt class="font-medium text-slate-500">Workspace account</dt>
                <dd class="text-right font-semibold text-slate-900">{{ $accountEmail ?: 'Not configured' }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-2.5">
                <dt class="font-medium text-slate-500">OAuth token</dt>
                <dd class="text-right font-semibold {{ $connected ? 'text-[#146e72]' : 'text-amber-700' }}">{{ $connected ? 'Connected' : 'Action Required' }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-2.5">
                <dt class="font-medium text-slate-500">Token expires</dt>
                <dd class="text-right tabular-nums text-slate-900">{{ $tokenExpiresAt?->format('M j, Y g:i A') ?? 'No stored token' }}</dd>
            </div>
        </dl>

        @if (! $connected)
            <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-sm text-amber-800">
                Reconnect the workspace account to restore Drive and Gmail access.
            </p>
        @endif

        <div class="mt-5 flex flex-wrap items-center justify-end gap-2.5 border-t border-slate-100 pt-4">
            <a href="{{ route('google-drive.connect') }}" class="rounded-lg bg-[#1a8a8e] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#146e72]">
                {{ $connected ? 'Reconnect Google Workspace' : 'Connect Google Workspace' }}
            </a>
            @if ($connection)
                <form method="POST" action="{{ route('admin.settings.google.disconnect') }}" onsubmit="return confirm('Disconnect the Google Workspace account? Drive and Gmail actions will stop until it is reconnected.')">
                    @csrf
                    <button type="submit" class="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50">Disconnect Account</button>
                </form>
            @endif
        </div>
    </section>

    <section class="ui-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Drive Folder Settings</h2>
                <p class="mt-1 max-w-prose text-sm leading-relaxed text-slate-500">Forms and documents are synced into this Google Drive folder.</p>
            </div>
            <span role="status" class="ui-badge {{ $folderId ? 'bg-[#eaf6f6] text-[#146e72] ring-[#1a8a8e]/20' : 'bg-amber-50 text-amber-700 ring-amber-200' }}">
                <span class="ui-badge-dot"></span>
                {{ $folderId ? 'Root Folder Ready' : 'No Root Folder' }}
            </span>
        </div>

        @if ($folderId)
            <p class="mt-4 text-sm text-slate-500">
                Current root folder:
                <span class="font-mono font-semibold text-slate-900">{{ '••••••••'.substr($folderId, -4) }}</span>
            </p>
        @else
            <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-sm text-amber-800">
                No target folder is configured. Auto-create one or paste an existing folder ID below.
            </p>
        @endif

        <form method="POST" action="{{ route('admin.settings.google.update-folder') }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <label for="folder_id" class="ui-label">Google Drive folder ID</label>
                <input id="folder_id" name="folder_id" value="" required maxlength="255" placeholder="Paste a new Google Drive folder ID to replace current" class="ui-input {{ $errors->has('folder_id') ? 'border-red-300 focus:border-red-400' : '' }}">
                @error('folder_id')
                    <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-2 max-w-prose text-xs leading-relaxed text-slate-500">Copy the ID from the folder URL: <span class="font-mono">drive.google.com/drive/folders/&lt;FOLDER_ID&gt;</span>.</p>
            </div>
            <div class="flex items-center justify-end gap-2.5 border-t border-slate-100 pt-4">
                <button type="submit" class="rounded-lg bg-[#1a8a8e] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#146e72]">Save folder</button>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.settings.google.create-folder') }}" class="mt-3">
            @csrf
            <button type="submit" class="w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                Auto-Create Drive Storage Folder
            </button>
        </form>
    </section>
</div>
@endsection
