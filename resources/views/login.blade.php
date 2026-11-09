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

            <div class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">Or continue with</span>
                    </div>
                </div>

                <div class="mt-6">
                    <a href="{{ route('auth.google') }}"
                       class="w-full flex items-center justify-center gap-3 px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        <svg class="w-5 h-5" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                        Sign in with Google
                    </a>
                </div>
            </div>
        </div>

        <div class="mt-6 text-center text-sm text-slate-500">
            <p>New to the ecosystem? Explore our public forms:</p>
            <div class="mt-2 flex flex-wrap justify-center gap-x-4 gap-y-1 font-medium">
                <a href="{{ route('public.startup-form') }}" class="text-[#b92369] hover:text-[#9a1b55]">Startup intake</a>
                <a href="{{ route('contact-registration') }}" class="text-slate-600 hover:text-slate-900">Contact registration</a>
            </div>
        </div>

        <p class="mt-8 text-center text-xs text-slate-500">Secure access for the Marian TBI team.</p>
    </section>
</main>
</body>
</html>
