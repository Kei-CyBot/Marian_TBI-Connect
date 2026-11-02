<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>You are invited: {{ $event->title }}</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <div style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;">
        <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#1a8a8e;">
            Event invitation
        </p>
        <h1 style="margin:0 0 12px;font-size:22px;">{{ $event->title }}</h1>
        <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">{{ $event->kind }}</p>

        <dl style="margin:0 0 24px;font-size:14px;line-height:1.8;color:#334155;">
            <dt style="font-weight:600;">Host</dt>
            <dd style="margin:0 0 8px;">{{ $event->host }}</dd>
            <dt style="font-weight:600;">Location</dt>
            <dd style="margin:0 0 8px;">{{ $event->location }}</dd>
            <dt style="font-weight:600;">Starts</dt>
            <dd style="margin:0 0 8px;">{{ $event->starts_at->format('g:i A - M d, Y') }}</dd>
            <dt style="font-weight:600;">Ends</dt>
            <dd style="margin:0;">{{ $event->ends_at->format('g:i A - M d, Y') }}</dd>
        </dl>

        <div style="margin:0 0 20px;">
            <a href="{{ url(route('events.invitations.respond', ['token' => $invitation->token, 'status' => 'attending'])) }}" style="display: inline-block; background-color: #16a34a; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin-right: 10px;">Attend</a>
            <a href="{{ url(route('events.invitations.respond', ['token' => $invitation->token, 'status' => 'declined'])) }}" style="display: inline-block; background-color: #dc2626; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Decline</a>
        </div>

        <p style="margin:0;font-size:13px;line-height:1.6;color:#64748b;">
            No response is recorded as <strong>Pending</strong>. You can use these links any time before the event.
        </p>
    </div>
</body>
</html>
