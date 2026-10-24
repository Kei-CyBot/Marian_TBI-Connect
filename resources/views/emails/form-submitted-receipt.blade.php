<p>Hi {{ $submission->data['signer_name'] ?? ($submission->user->name ?? '') }},</p>
<p>Your submission "{{ $submission->form_name }}" for {{ $submission->startup_name }} has been received.</p>
<p>Thank you.</p>
