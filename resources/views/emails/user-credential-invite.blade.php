<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your account credentials</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:28px;">
                    <tr>
                        <td style="font-size:16px;line-height:1.6;">
                            <p style="margin:0 0 16px;">Hi,</p>
                            <p style="margin:0 0 16px;">Your Marian TBI Connect account credentials are provided below.</p>
                            <p style="margin:0 0 8px;"><strong>Email:</strong> {{ $email }}</p>
                            <p style="margin:0 0 16px;"><strong>Temporary Password:</strong> {{ $tempPassword }}</p>
                            <p style="margin:0 0 16px;"><strong>Login URL:</strong> <a href="{{ $loginUrl }}" style="color:#1a8a8e;">{{ $loginUrl }}</a></p>
                            <p style="margin:0;">Sign in with the temporary password above and set up your personal account password on <strong>/profile</strong> to complete your setup.</p>
                            <p style="margin:16px 0 0;">— Marian TBI Connect</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
