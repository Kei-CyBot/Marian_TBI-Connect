<x-mail::message>
# Application received

Hi {{ $submission->name }},

{{ $submission->type === 'startup' ? 'We received your startup application' : 'We received your alumni progress update' }}. Our team will review it and contact you at {{ $submission->email }}.

**Tracking ID:** {{ $submission->trackingId() }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
