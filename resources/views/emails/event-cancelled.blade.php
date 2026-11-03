<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Event cancelled: {{ $event->title }}</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <div style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;">
        <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#b92369;">
            Event cancelled
        </p>
        <h1 style="margin:0 0 12px;font-size:22px;">{{ $event->title }}</h1>
        <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
            We are sorry to let you know that this event has been cancelled.
        </p>

        <dl style="margin:0 0 20px;font-size:14px;line-height:1.8;color:#334155;">
            <dt style="font-weight:600;">Host</dt>
            <dd style="margin:0 0 8px;">{{ $event->host }}</dd>
            <dt style="font-weight:600;">Location</dt>
            <dd style="margin:0 0 8px;">{{ $event->location }}</dd>
            <dt style="font-weight:600;">Was scheduled</dt>
            <dd style="margin:0 0 8px;">{{ $event->starts_at->format('g:i A - M d, Y') }}</dd>
            @if ($event->cancellation_reason)
                <dt style="font-weight:600;">Reason</dt>
                <dd style="margin:0;">{{ $event->cancellation_reason }}</dd>
            @endif
        </dl>

        <p style="margin:0;font-size:13px;line-height:1.6;color:#64748b;">
            No further action is needed. The RSVP link for this event is no longer active.
        </p>
    </div>
</body>
</html>
