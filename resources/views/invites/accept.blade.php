<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Accept invitation · Marian TBI Connect</title>
    <link rel="icon" type="image/png" href="{{ asset('marian-tbi-logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="min-h-full bg-[#F8FAFC] font-sans text-[#0F172A] antialiased">
<main class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-12">
    <section class="relative w-full max-w-md">
        <div class="mb-8 flex items-center justify-center gap-3">
            <img src="{{ asset('marian-tbi-logo.png') }}" alt="" class="h-12 w-12 object-contain">
            <span class="text-2xl font-bold tracking-tight">Marian TBI Connect</span>
        </div>

        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-7 shadow-xl shadow-slate-900/[0.04] sm:p-9">
            <div class="mb-7 text-center">
                <h1 class="text-2xl font-bold tracking-tight">Set up your account</h1>
                <p class="mt-2 text-sm text-slate-500">Invitation sent to {{ $invitation->email }}.</p>
            </div>

            @if ($errors->any())
                <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="mt-1">{{ $errors->first() }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('invites.accept.process', $token) }}" class="space-y-5">
                @csrf
                <div>
                    <label for="name" class="mb-1.5 block text-sm font-semibold text-slate-700">Full name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required autofocus class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                </div>
                <div>
                    <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-slate-700">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                </div>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-[#1a8a8e] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#146e72] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/40 focus:ring-offset-2">Create account</button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
