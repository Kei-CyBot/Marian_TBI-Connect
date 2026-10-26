<p>Hi {{ $submission->name }},</p>
<p>{{ $submission->type === 'startup' ? 'We received your startup application' : 'We received your alumni progress update' }}. Our team will review it and contact you at {{ $submission->email }}.</p>
<p>Thank you.</p>
