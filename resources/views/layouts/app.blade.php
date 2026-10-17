<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Marian TBI Connect</title>
    <link rel="icon" type="image/png" href="{{ asset('marian-tbi-logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }
        .grad { background-image: linear-gradient(to right, #b92369, #1a8a8e); }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 9999px; }
    </style>
    @include('partials.forms-catalog')
    @stack('head')
</head>
<body class="h-full bg-slate-canvas font-sans text-slate-ink antialiased"
      x-data="{ sidebarOpen: false, profileOpen: false, scope: 'all' }"
      @keydown.escape.window="sidebarOpen = false; profileOpen = false">
<div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
     class="fixed inset-0 z-30 bg-slate-900/40 backdrop-blur-sm lg:hidden"></div>

<aside class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-slate-line bg-white transition-transform duration-200 lg:translate-x-0"
       :class="{ 'translate-x-0': sidebarOpen }">
    <a href="{{ route('dashboard') }}" class="flex h-16 items-center gap-3 border-b border-gray-100 px-5">
        <img src="{{ asset('marian-tbi-logo.png') }}" alt="" class="h-10 w-10 object-contain">
        <span class="leading-tight">
            <span class="block text-[15px] font-bold tracking-tight">Marian TBI Connect</span>
            <span class="block text-[11px] font-medium uppercase tracking-wider text-gray-400">Incubation Management</span>
        </span>
    </a>

    @php
        $nav = [
            ['Dashboard', 'dashboard', 'M3 12l9-9 9 9M5 10v10h14V10'],
            ['Incubatees & Cohorts', 'incubatees', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3M9 7h1m-1 4h1m4-4h1m-1 4h1'],
            ['Contacts & Network', 'contacts', 'M17 20h5v-2a4 4 0 00-3-3.9M9 20H4v-2a4 4 0 013-3.9m6-4.1a4 4 0 11-8 0 4 4 0 018 0zm6 0a3 3 0 11-6 0'],
            ['Contact Intake & Accounts', 'contact-intake', 'M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2m6-10a4 4 0 100-8 4 4 0 000 8zm8-1v6m3-3h-6'],
            ['File Drive & Documents', 'files', 'M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z'],
            ['Technical Needs', 'technical-needs', 'M9 3h6m-5 0v5.2L4.5 18a2 2 0 001.7 3h11.6a2 2 0 001.7-3L14 8.2V3m-7 11h10'],
            ['Events & Outreach', 'events', 'M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z'],
            ['Team & Access Control', 'team', 'M12 11c1.7 0 3-1.3 3-3s-1.3-3-3-3-3 1.3-3 3 1.3 3 3 3zm0 2c-2.8 0-6 1.4-6 4v2h12v-2c0-2.6-3.2-4-6-4z'],
            ['Archives', 'archives', 'M5 8h14M5 8a2 2 0 010-4h14a2 2 0 010 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4'],
        ];
    @endphp
    <nav aria-label="Main navigation" class="mt-4 flex-1 space-y-0.5 overflow-y-auto px-4 pb-4">
        @foreach($nav as [$label, $path, $icon])
            @php $active = request()->is($path) || request()->is($path.'/*'); @endphp
            <a href="{{ url('/'.$path) }}"
               @click="sidebarOpen = false"
               @class([
                   'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                   'bg-[#fcf0f5] text-[#b92369]' => $active,
                   'text-gray-600 hover:bg-gray-50 hover:text-slate-900' => ! $active,
               ])>
                <svg class="h-[18px] w-[18px] {{ $active ? 'text-[#b92369]' : 'text-gray-400 group-hover:text-gray-600' }}"
                     fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/>
                </svg>
                <span class="truncate">{{ $label }}</span>
                @if($active)<span class="ml-auto h-1.5 w-1.5 rounded-full bg-[#b92369]"></span>@endif
            </a>
        @endforeach
    </nav>

    @php
        $currentUser = auth()->user();
        $userName = $currentUser?->name ?? 'Admin User';
        $userInitials = collect(explode(' ', trim($userName)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    @endphp
    <div class="border-t border-gray-100 p-4">
        <div class="flex items-center gap-3 rounded-2xl bg-gray-50 p-2.5">
            <span class="grad flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white">{{ $userInitials }}</span>
            <span class="min-w-0 leading-tight">
                <span class="block truncate text-sm font-semibold">{{ $userName }}</span>
                <span class="block truncate text-xs text-gray-500">{{ $currentUser?->email ?? 'Marian TBI team' }}</span>
            </span>
        </div>
    </div>
</aside>

<div class="lg:pl-72">
    <header class="sticky top-0 z-20 border-b border-slate-line bg-white/90 backdrop-blur">
        <div class="flex min-h-16 flex-wrap items-center justify-between gap-3 px-4 py-2 sm:px-6">
            <button type="button" @click="sidebarOpen = true" aria-label="Open navigation"
                    class="rounded-xl border border-gray-200 p-2 text-gray-600 hover:bg-gray-50 lg:hidden">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <div class="flex flex-1 items-center justify-end gap-2">
                @if ($currentUser?->canManageRecords())
                    <button type="button" @click="$dispatch('open-incubatee-onboarding')"
                            @if (! request()->routeIs('incubatees')) onclick="window.location.href='{{ route('incubatees', ['openOnboarding' => '1']) }}'" @endif
                            class="inline-flex items-center gap-1.5 rounded-xl bg-[#b92369] px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#9a1b55] active:scale-[.98]">
                        <span class="text-base leading-none" aria-hidden="true">+</span> Add Incubatee
                    </button>
                    <button type="button" @click="$dispatch('open-contact-form')"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-[#1a8a8e] px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#146e72] active:scale-[.98]">
                        <span class="text-base leading-none" aria-hidden="true">+</span> Register Contact
                    </button>
                @endif

                <div class="relative" @click.outside="profileOpen = false">
                    <button type="button" @click="profileOpen = !profileOpen" :aria-expanded="profileOpen"
                            aria-label="Open user profile menu"
                            class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white p-1.5 pr-2.5 transition hover:bg-gray-50">
                        <span class="grad flex h-8 w-8 items-center justify-center rounded-lg text-xs font-bold text-white">{{ $userInitials }}</span>
                        <span class="hidden max-w-32 truncate text-sm font-medium text-slate-700 sm:block">{{ $userName }}</span>
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>
                    <div x-show="profileOpen" x-cloak x-transition.origin.top.right
                         class="absolute right-0 z-30 mt-2 w-56 rounded-2xl border border-gray-100 bg-white p-2 shadow-xl">
                        <div class="border-b border-gray-100 px-3 py-2">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $userName }}</p>
                            <p class="truncate text-xs text-gray-500">{{ $currentUser?->email ?? '' }}</p>
                            <p class="mt-1 text-xs font-medium text-[#146e72]">{{ $currentUser?->roleLabel() ?? '' }}</p>
                        </div>
                        @auth
                            <a href="{{ route('profile.edit') }}" class="mt-1 block rounded-xl px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">My profile & credentials</a>
                            <form method="POST" action="{{ route('logout') }}" class="pt-1">
                                @csrf
                                <button type="submit" class="w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Sign out
                                </button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="mt-1 block rounded-xl px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Sign in</a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="px-4 py-6 sm:px-6">
        <section class="grad relative mb-6 overflow-hidden rounded-2xl px-6 py-6 text-white shadow-lg shadow-[#b92369]/10">
            <div class="pointer-events-none absolute -right-10 -top-16 h-56 w-56 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-20 right-32 h-48 w-48 rounded-full bg-white/10"></div>
            <div class="relative flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-white/70">@yield('eyebrow', 'Marian TBI Connect')</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight">@yield('heading', 'Dashboard')</h1>
                    <p class="mt-1 max-w-2xl text-sm text-white/80">@yield('subheading')</p>
                </div>
                <div class="flex gap-2">@yield('banner-actions')</div>
            </div>
        </section>
        @yield('content')
    </main>
</div>

@include('partials.incubatee-slideover')
@include('partials.contact-slideover')
@include('partials.event-slideover')
@stack('scripts')
</body>
</html>
