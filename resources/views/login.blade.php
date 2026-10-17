<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in · Marian TBI Connect</title>
    <link rel="icon" type="image/png" href="{{ asset('marian-tbi-logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="min-h-full bg-[#F8FAFC] font-sans text-[#0F172A] antialiased">
<main class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-12">
    <div class="pointer-events-none absolute -left-24 -top-28 h-80 w-80 rounded-full bg-[#b92369]/10 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-32 -right-20 h-96 w-96 rounded-full bg-[#1a8a8e]/10 blur-3xl"></div>

    <section class="relative w-full max-w-md">
        <a href="{{ url('/') }}" class="mb-8 flex items-center justify-center gap-3">
            <img src="{{ asset('marian-tbi-logo.png') }}" alt="" class="h-12 w-12 object-contain">
            <span class="text-2xl font-bold tracking-tight">Marian TBI Connect</span>
        </a>

        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-7 shadow-xl shadow-slate-900/[0.04] sm:p-9">
            <div class="mb-7 text-center">
                <h1 class="text-2xl font-bold tracking-tight">Welcome back</h1>
                <p class="mt-2 text-sm text-slate-500">Sign in to your incubation management workspace.</p>
            </div>

            @if ($errors->any())
                <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">We couldn't sign you in.</p>
                    <p class="mt-1">{{ $errors->first() }}</p>
                </div>
            @endif

            @if (session('status'))
                <div role="status" class="mb-5 rounded-xl border border-[#1a8a8e]/20 bg-[#eaf6f6] px-4 py-3 text-sm text-[#146e72]">
                    {{ session('status') }}
                </div>
            @endif

            <a href="{{ route('auth.google.redirect') }}"
               class="group inline-flex w-full items-center justify-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-gray-300 hover:bg-gray-50 hover:shadow focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/30 focus:ring-offset-2">
                <svg class="h-5 w-5 transition-transform group-hover:scale-105" viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.76 7.18l7.73 6C44.42 37.94 46.98 31.87 46.98 24.55z"/>
                    <path fill="#FBBC05" d="M10.53 28.59A14.4 14.4 0 019.75 24c0-1.59.27-3.13.76-4.59l-7.98-6.19A23.9 23.9 0 000 24c0 3.87.93 7.54 2.56 10.78l7.97-6.19z"/>
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.91-5.8l-7.73-6c-2.14 1.44-4.89 2.3-8.18 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                </svg>
                <span>Sign in with Google</span>
            </a>

            <div class="my-6 flex items-center gap-4" aria-hidden="true">
                <span class="h-px flex-1 bg-gray-200"></span>
                <span class="text-xs font-medium uppercase tracking-wider text-gray-400">Or continue with email</span>
                <span class="h-px flex-1 bg-gray-200"></span>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                @csrf
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">Work email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username"
                           required autofocus
                           @class([
                               'w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-gray-400 focus:outline-none focus:ring-2',
                               'border-red-300 focus:border-red-500 focus:ring-red-500/15' => $errors->has('email'),
                               'border-gray-200 focus:border-[#1a8a8e] focus:ring-[#1a8a8e]/15' => ! $errors->has('email'),
                           ])
                           @if($errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif>
                    @error('email')
                        <p id="email-error" class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>
                    </div>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-gray-400 focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/15">
                </div>

                <label class="flex items-center gap-2.5 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))
                           class="h-4 w-4 rounded border-gray-300 text-[#b92369] focus:ring-[#b92369]">
                    Remember me
                </label>

                <button type="submit"
                        class="w-full rounded-xl bg-[#b92369] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#9a1b55] focus:outline-none focus:ring-2 focus:ring-[#b92369]/40 focus:ring-offset-2 active:scale-[.99]">
                    Sign in
                </button>
            </form>
        </div>

        <div class="mt-6 text-center text-sm text-slate-500">
            <p>New to the ecosystem? Explore our public forms:</p>
            <div class="mt-2 flex flex-wrap justify-center gap-x-4 gap-y-1 font-medium">
                <a href="{{ route('startup-form') }}" class="text-[#b92369] hover:text-[#9a1b55]">Startup intake</a>
                <a href="{{ route('alumni-form') }}" class="text-[#1a8a8e] hover:text-[#146e72]">Alumni form</a>
                <a href="{{ route('contact-registration') }}" class="text-slate-600 hover:text-slate-900">Contact registration</a>
            </div>
        </div>

        <p class="mt-8 text-center text-xs text-slate-500">Secure access for the Marian TBI team.</p>
    </section>
</main>
</body>
</html>
