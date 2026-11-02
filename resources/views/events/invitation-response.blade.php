<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RSVP · {{ $event->title }}</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:32px;">
        <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#1a8a8e;">
            RSVP confirmed
        </p>
        <h1 style="margin:0 0 16px;font-size:22px;">{{ $event->title }}</h1>
        <p style="margin:0 0 8px;font-size:15px;line-height:1.6;">
            Your response has been recorded as
            <strong>{{ $invitation->status === 'attending' ? 'Attending' : 'Declined' }}</strong>.
        </p>
        <dl style="margin:16px 0 0;font-size:14px;line-height:1.8;color:#334155;">
            <dt style="font-weight:600;">Host</dt>
            <dd style="margin:0 0 8px;">{{ $event->host }}</dd>
            <dt style="font-weight:600;">Starts</dt>
            <dd style="margin:0 0 8px;">{{ $event->starts_at->format('g:i A - M d, Y') }}</dd>
            <dt style="font-weight:600;">Ends</dt>
            <dd style="margin:0;">{{ $event->ends_at->format('g:i A - M d, Y') }}</dd>
        </dl>
    </div>
</body>
</html>
