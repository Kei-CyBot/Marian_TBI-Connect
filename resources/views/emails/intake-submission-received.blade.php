<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Application received</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:28px;">
                    <tr>
                        <td style="font-size:16px;line-height:1.6;">
                            <p style="margin:0 0 16px;">Hi {{ $submission->name }},</p>
                            <p style="margin:0 0 16px;">
                                {{ $submission->type === 'startup' ? 'We received your startup application' : 'We received your alumni progress update' }}.
                                Our team will review it and contact you at {{ $submission->email }}.
                            </p>
                            <p style="margin:0 0 16px;padding:12px 16px;background:#f1f5f9;border-radius:8px;">
                                <strong>Tracking ID:</strong> {{ $submission->trackingId() }}
                            </p>
                            <p style="margin:0;">Thank you.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
