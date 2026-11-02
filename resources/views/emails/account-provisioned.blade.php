<x-mail::message>
# Your account is ready

An account has been created for **{{ $email }}**.

**Temporary password:** {{ $temporaryPassword }}

<x-mail::button :url="$loginUrl">
Sign in
</x-mail::button>

After signing in, change your password in Profile Settings.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
