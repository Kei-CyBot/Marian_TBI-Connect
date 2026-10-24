@php
    $submission = isset($submission) && $submission instanceof \App\Models\FormSubmission ? $submission : null;
    $data = $submission?->data ?? [];
@endphp

<div class="space-y-4">
    <div class="rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-[#146e72]">Filed in this incubatee folder</p>
        <p class="mt-1 text-sm text-gray-800">The form response is stored here with its uploaded attachment, if provided.</p>
        @if ($submission)
            <p class="mt-2 text-xs text-gray-700">
                Submitted {{ $submission->created_at?->format('M j, Y') ?? '—' }}
                @if ($submission->prerequisite_original_name)
                    · Attachment: <span class="font-semibold">{{ $submission->prerequisite_original_name }}</span>
                @endif
            </p>
        @endif
    </div>

    <x-form-data-viewer :data="$data" />
</div>
