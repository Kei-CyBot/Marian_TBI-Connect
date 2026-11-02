<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Application Status · Marian TBI Connect</title>
    <link rel="icon" type="image/png" href="{{ asset('marian-tbi-logo.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:Inter,ui-sans-serif,system-ui,sans-serif}</style>
</head>
<body class="min-h-screen bg-[#F8FAFC] text-[#0F172A] antialiased">
    <header class="mx-auto flex max-w-3xl items-center gap-3 px-6 pt-8">
        <img src="{{ asset('marian-tbi-logo.png') }}" alt="" class="h-10 w-10 object-contain">
        <p class="font-bold">Marian TBI Connect</p>
    </header>
    <main class="mx-auto max-w-3xl px-6 py-8">
        <section class="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-[#1a8a8e]">Marian TBI Incubation Program</p>
            <h1 class="mt-2 text-2xl font-bold">Your application is under review</h1>
            @if (session('info'))
                <div role="status" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">{{ session('info') }}</div>
            @else
                <p class="mt-2 text-sm leading-6 text-gray-600">Your application is currently under admin review. The Marian TBI team will contact you using the email address provided in your application.</p>
            @endif
            <p class="mt-5 text-sm text-gray-600">
                Questions? Contact
                <a class="font-semibold text-[#146e72] underline focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]" href="mailto:{{ config('mail.admin_address', config('mail.from.address')) }}">{{ config('mail.admin_address', config('mail.from.address')) }}</a>.
            </p>
            <a href="{{ route('home') }}" class="mt-6 inline-flex min-h-11 items-center rounded-xl bg-[#1a8a8e] px-5 py-3 text-sm font-semibold text-white hover:bg-[#146e72] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]">Return to homepage</a>
        </section>
    </main>
</body>
</html>
