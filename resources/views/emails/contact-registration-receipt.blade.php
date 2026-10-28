<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <p>Hi {{ $name }},</p>
    <p>Thank you for registering with Marian TBI Connect. We have received your application.</p>
    <p>Tracking: {{ $isInternal ? 'UIC Internal / Institutional' : 'External / General' }}.</p>
    <p>We will review your application and notify you of next steps.</p>
    <p>— Marian TBI Connect</p>
</body>
</html>
