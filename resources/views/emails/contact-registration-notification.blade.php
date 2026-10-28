<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <p>Hi Admin,</p>
    <p>A new contact registration application has been submitted.</p>
    <p><strong>Name:</strong> {{ $application->name }}</p>
    <p><strong>Email:</strong> {{ $application->email }}</p>
    <p><strong>Stream:</strong> {{ $application->stream }}</p>
    <p>Please review it in the Contact Intake dashboard.</p>
    <p>— Marian TBI Connect</p>
</body>
</html>
