<x-mail::message>
# One-time sign-in link

Use the button below to securely sign in to Marian TBI Connect and set up your personal account password. This link expires in 15 minutes and can only be used once.

<x-mail::button :url="$loginUrl">
Sign in and set up my password
</x-mail::button>

If you did not request this link, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
