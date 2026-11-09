<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Apply · Marian TBI Connect</title>
    <link rel="icon" type="image/png" href="{{ asset('marian-tbi-logo.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:Inter,ui-sans-serif,system-ui,sans-serif}</style>
</head>
<body class="min-h-screen bg-[#F8FAFC] text-[#0F172A] antialiased">

    <header class="mx-auto flex max-w-3xl items-center gap-3 px-6 pt-8">
        <img src="{{ asset('marian-tbi-logo.png') }}" alt="" class="h-10 w-10 object-contain">
        <p class="font-bold">Marian TBI Connect</p>
        <a href="{{ route('login') }}" class="ml-auto text-sm font-semibold text-[#1a8a8e] hover:text-[#146e72]">← Return to Login</a>
    </header>

    <main class="mx-auto max-w-3xl px-6 py-8">
        @if (session('info'))
            <div role="status" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">{{ session('info') }}</div>
        @endif
        @if (session('error'))
            <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ session('error') }}</div>
        @endif
        @if (session('intake_status'))
            <div role="status" class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
                <span>{{ session('intake_status') }}</span>
                <a href="{{ route('login') }}" class="rounded-xl bg-[#1a8a8e] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#146e72]">Return to Login</a>
            </div>
        @endif
        @if ($errors->any())
            <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                <p class="font-semibold">Please correct the following:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <section class="rounded-2xl bg-gradient-to-r from-[#b92369] to-[#1a8a8e] p-8 text-white shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wider text-white/70">Public Intake Portal</p>
            <h1 class="mt-1 text-2xl font-bold">Apply to the Incubation Program</h1>
            <p class="mt-1 text-sm text-white/85">Tell us about your startup and how to reach you. Our team reviews every application.</p>
        </section>

        <form method="POST" action="{{ route('startup-form.store') }}" class="mt-6 space-y-5 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="mb-1 block text-xs font-semibold text-slate-600">Founder full name</label>
                    <input id="name" required name="name" value="{{ old('name', session('oauth_register.name')) }}"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/30">
                </div>
                <div>
                    <label for="email" class="mb-1 block text-xs font-semibold text-slate-600">Email</label>
                    <input id="email" required type="email" name="email" value="{{ old('email', session('oauth_register.email')) }}"
                           @readonly(filled(session('oauth_register.email')))
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/30 @if(session('oauth_register.email')) bg-gray-50 text-gray-500 @endif">
                    @if (session('oauth_register.email'))
                        <p role="status" class="mt-1 text-sm font-medium text-emerald-700">✓ Verified via Google Account</p>
                    @endif
                </div>
                <div>
                    <label for="phone" class="mb-1 block text-xs font-semibold text-slate-600">Contact number</label>
                    <input id="phone" type="tel" name="phone" value="{{ old('phone') }}"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/30">
                </div>
                <div>
                    <label for="internal_external" class="mb-1 block text-xs font-semibold text-slate-600">Applicant stream</label>
                    <select id="internal_external" required name="internal_external"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/30">
                        <option value="Internal" @selected(old('internal_external') === 'Internal')>Internal</option>
                        <option value="External" @selected(old('internal_external', 'External') === 'External')>External</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="startup_name" class="mb-1 block text-xs font-semibold text-slate-600">Startup name</label>
                    <input id="startup_name" required name="startup_name" value="{{ old('startup_name') }}"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/30">
                </div>
            </div>

            <div>
                <label for="description" class="mb-1 block text-xs font-semibold text-slate-600">Brief pitch / description <span class="font-normal text-gray-400">(optional)</span></label>
                <textarea id="description" rows="4" name="description"
                          class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/30">{{ old('description') }}</textarea>
            </div>

            <button class="w-full rounded-xl bg-[#1a8a8e] py-2.5 text-sm font-semibold text-white transition hover:bg-[#146e72] active:scale-[.99]">Submit Application</button>
        </form>
    </main>
</body>
</html>
