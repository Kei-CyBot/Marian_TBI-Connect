<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <p>Hi,</p>
    <p>Your Marian TBI Connect account credentials are provided below.</p>
    <p><strong>Email:</strong> {{ $email }}</p>
    <p><strong>Temporary Password:</strong> {{ $tempPassword }}</p>
    <p><strong>Login URL:</strong> <a href="{{ $loginUrl }}">{{ $loginUrl }}</a></p>
    <p>Please log in and update your password on <strong>/profile</strong> after logging in.</p>
    <p>— Marian TBI Connect</p>
</body>
</html>
