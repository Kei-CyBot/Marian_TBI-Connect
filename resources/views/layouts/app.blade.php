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
<body class="h-full bg-slate-50/70 font-sans text-slate-900 antialiased"
      x-data="{ sidebarOpen: false, profileOpen: false, scope: 'all' }"
      @keydown.escape.window="sidebarOpen = false; profileOpen = false">
@php
    $currentUser = auth()->user();
    $isManager = $currentUser?->canManageRecords() ?? false;
    $isSuperAdmin = $currentUser?->isSuperAdmin() ?? false;

    $pendingStartupCount = $isManager
        ? \App\Models\IntakeSubmission::query()
            ->where('type', 'startup')
            ->whereRaw('LOWER(status) = ?', ['pending'])
            ->count()
        : 0;

    $sections = [
        'operations' => [
            'label' => 'Incubatee Operations',
            'items' => [
                [
                    'label' => 'Incubatees',
                    'route' => 'incubatees',
                    'active' => ['incubatees', 'incubatees.*', 'startup-assessments.*', 'startup-drive-links.*'],
                    'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3M9 7h1m-1 4h1m4-4h1m-1 4h1',
                    'badge' => $pendingStartupCount,
                    'shortcut' => 'Alt I',
                    'key' => 'i',
                ],
                [
                    'label' => 'Technical Needs',
                    'route' => 'technical-needs',
                    'active' => ['technical-needs', 'technical-needs.*'],
                    'icon' => 'M9 3h6m-5 0v5.2L4.5 18a2 2 0 001.7 3h11.6a2 2 0 001.7-3L14 8.2V3m-7 11h10',
                    'shortcut' => 'Alt T',
                    'key' => 't',
                ],
                [
                    'label' => 'Files',
                    'route' => 'files',
                    'active' => ['files', 'files.*', 'form-submissions.*'],
                    'icon' => 'M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z',
                    'shortcut' => 'Alt F',
                    'key' => 'f',
                ],
            ],
        ],
        'network' => [
            'label' => 'Ecosystem & Network',
            'items' => array_values(array_filter([
                $isManager ? [
                    'label' => 'Contacts',
                    'route' => 'contacts',
                    'active' => ['contacts', 'contacts.*', 'startup-folders.*'],
                    'icon' => 'M17 20h5v-2a4 4 0 00-3-3.9M9 20H4v-2a4 4 0 013-3.9m6-4.1a4 4 0 11-8 0 4 4 0 018 0zm6 0a3 3 0 11-6 0',
                    'shortcut' => 'Alt C',
                    'key' => 'c',
                    'children' => [
                        [
                            'label' => 'Contact Intake',
                            'route' => 'contact-intake',
                            'active' => ['contact-intake', 'contact-intake.*'],
                            'icon' => 'M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2m6-10a4 4 0 100-8 4 4 0 000 8zm8-1v6m3-3h-6',
                        ],
                    ],
                ] : null,
                [
                    'label' => 'Events',
                    'route' => 'events',
                    'active' => ['events', 'events.*', 'event-emails.*', 'event-outreach.*'],
                    'icon' => 'M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z',
                    'shortcut' => 'Alt E',
                    'key' => 'e',
                ],
            ])),
        ],
    ];

    if ($isManager) {
        $adminItems = [
            [
                'label' => 'Reports',
                'route' => 'reports.index',
                'active' => ['reports', 'reports.*'],
                'icon' => 'M9 17v-6m3 6V7m3 10v-3M5 21h14a1 1 0 001-1V4a1 1 0 00-1-1H5a1 1 0 00-1 1v16a1 1 0 001 1z',
                'shortcut' => 'Alt R',
                'key' => 'r',
                'children' => [
                    [
                        'label' => 'Export CSV',
                        'route' => 'reports.export-csv',
                        'active' => ['reports.export-csv'],
                        'icon' => 'M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0 0 4-4m-4 4-4-4',
                    ],
                ],
            ],
            [
                'label' => 'Activity Logs',
                'route' => 'activity-logs.index',
                'active' => ['activity-logs.*'],
                'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 8h6m-6 4h6',
            ],
            [
                'label' => 'Archives',
                'route' => 'archives.index',
                'active' => ['archives.*'],
                'icon' => 'M5 8h14M5 8a2 2 0 010-4h14a2 2 0 010 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
            ],
            [
                'label' => 'User & Credential Management',
                'route' => 'team',
                'active' => ['team', 'team.*'],
                'icon' => 'M12 11c1.7 0 3-1.3 3-3s-1.3-3-3-3-3 1.3-3 3 1.3 3 3 3zm0 2c-2.8 0-6 1.4-6 4v2h12v-2c0-2.6-3.2-4-6-4z',
            ],
        ];

        if ($isSuperAdmin) {
            $adminItems[] = [
                'label' => 'Google Workspace',
                'route' => 'admin.settings.google.index',
                'active' => ['admin.settings.google.*', 'google-drive.*', 'auth.google.*'],
                'icon' => 'M21 12a9 9 0 11-5.6-8.3M21 3v6h-6',
            ];
        }

        $sections['administration'] = [
            'label' => 'Administration',
            'items' => $adminItems,
        ];
    }

    $activeSection = null;
    foreach ($sections as $sectionKey => $section) {
        foreach ($section['items'] as $item) {
            if (request()->routeIs(...$item['active'])) {
                $activeSection = $sectionKey;
                break 2;
            }
        }
    }

    $quickAction = $isManager
        ? ['label' => 'Technical Needs', 'route' => 'technical-needs', 'shortcut' => 'Alt T', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z']
        : ['label' => 'My Files', 'route' => 'files', 'shortcut' => 'Alt F', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'];

    $shortcuts = [
        'd' => route('dashboard'),
        'i' => route('incubatees'),
        't' => route('technical-needs'),
        'f' => route('files'),
        'e' => route('events'),
    ];

    if ($isManager) {
        $shortcuts['c'] = route('contacts');
        $shortcuts['r'] = route('reports.index');
    }

    $userName = $currentUser?->name ?? 'Admin User';
    $userInitials = collect(explode(' ', trim($userName)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
@endphp

<div x-data="appSidebar({ activeSection: @js($activeSection), shortcuts: @js($shortcuts) })"
     @keydown.window="handleShortcut($event)"
     @keyup.window="if ($event.key === 'Alt') altHeld = false"
     @blur.window="altHeld = false">
    <div class="fixed inset-x-0 top-0 z-50 h-[2px] bg-gradient-to-r from-rose-800/75 via-rose-700/50 to-teal-700/75"></div>
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
         class="fixed inset-0 z-30 bg-slate-900/40 backdrop-blur-sm lg:hidden"></div>

    <aside class="fixed inset-y-0 left-0 z-40 flex w-[272px] -translate-x-full flex-col border-r border-slate-200/80 bg-white transition-[transform,width] duration-200 lg:translate-x-0"
           :class="{ 'translate-x-0': sidebarOpen, 'lg:w-[72px]': collapsed, 'lg:w-[272px]': ! collapsed }">
        <a href="{{ route('dashboard') }}" class="flex h-16 shrink-0 items-center gap-2.5 border-b border-slate-200/70 px-4"
           :class="collapsed ? 'justify-center px-3' : ''">
            <img src="{{ asset('marian-tbi-logo.png') }}" alt="" class="h-9 w-9 shrink-0 object-contain">
            <span class="leading-tight" x-show="!collapsed">
                <span class="block text-[15px] font-semibold tracking-tight text-slate-900">Marian TBI Connect</span>
                <span class="block text-[11px] font-medium uppercase tracking-wider text-slate-400">Incubation Management</span>
            </span>
        </a>

        <nav aria-label="Main navigation" class="ui-scrollbar mt-3 flex-1 space-y-1 overflow-y-auto px-2.5 pb-4">
            <div class="space-y-0.5">
                @php $dashboardActive = request()->routeIs('dashboard'); @endphp
                <a href="{{ route('dashboard') }}" @click="sidebarOpen = false"
                   @if($dashboardActive) aria-current="page" @endif
                   data-label="Dashboard"
                   :title="collapsed ? $el.dataset.label : null"
                   :class="collapsed ? 'justify-center px-2' : ''"
                   class="group relative flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-[13.5px] font-medium leading-tight transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1a8a8e]/40 {{ $dashboardActive ? 'bg-rose-50/75 font-semibold text-rose-900' : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900' }}">
                    @if($dashboardActive)<span class="absolute inset-y-1.5 left-0 w-[2.5px] rounded-full bg-rose-700/85"></span>@endif
                    <svg class="w-5 h-5 shrink-0 {{ $dashboardActive ? 'text-rose-800' : 'text-slate-400 group-hover:text-slate-600' }}"
                         fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10h14V10"/>
                    </svg>
                    <span class="truncate" x-show="!collapsed">Dashboard</span>
                    <span class="ml-auto flex items-center gap-1.5" x-show="!collapsed">
                        @if($dashboardActive)<span class="h-1.5 w-1.5 rounded-full bg-rose-700/85"></span>@endif
                        <kbd class="ui-kbd opacity-0 transition group-hover:opacity-100"
                              :class="altHeld ? 'opacity-100' : ''">Alt D</kbd>
                    </span>
                </a>

                <a href="{{ route($quickAction['route']) }}" @click="sidebarOpen = false"
                   data-label="{{ $quickAction['label'] }}"
                   :title="collapsed ? $el.dataset.label : null"
                   :class="collapsed ? 'justify-center px-2' : ''"
                   class="group flex items-center gap-2.5 rounded-lg border border-slate-200/90 bg-slate-50/90 px-2.5 py-1.5 text-[13.5px] font-medium leading-tight text-slate-700 transition hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1a8a8e]/40">
                    <svg class="w-5 h-5 shrink-0 text-teal-700" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $quickAction['icon'] }}"/>
                    </svg>
                    <span class="truncate" x-show="!collapsed">{{ $quickAction['label'] }}</span>
                    <span class="ml-auto flex items-center gap-1.5" x-show="!collapsed">
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Quick</span>
                        <kbd class="ui-kbd opacity-0 transition group-hover:opacity-100"
                              :class="altHeld ? 'opacity-100' : ''">{{ $quickAction['shortcut'] }}</kbd>
                    </span>
                </a>
            </div>

            @foreach($sections as $sectionKey => $section)
                <div class="space-y-0.5">
                    <button type="button" x-show="!collapsed" @click="toggle('{{ $sectionKey }}')"
                            :aria-expanded="isOpen('{{ $sectionKey }}')"
                            aria-controls="sidebar-section-{{ $sectionKey }}"
                            class="mt-5 mb-1.5 flex w-full items-center gap-2 rounded-md px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400 transition hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1a8a8e]/40">
                        <span class="flex-1 text-left">{{ $section['label'] }}</span>
                        <svg class="h-3.5 w-3.5 shrink-0 transition-transform duration-200"
                             :class="isOpen('{{ $sectionKey }}') ? 'rotate-90' : ''"
                             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/>
                        </svg>
                    </button>

                    <ul id="sidebar-section-{{ $sectionKey }}" x-show="collapsed || sections.{{ $sectionKey }}" class="space-y-0.5">
                        @foreach($section['items'] as $item)
                            @php $active = request()->routeIs(...$item['active']); @endphp
                            <li>
                                <a href="{{ route($item['route']) }}" @click="sidebarOpen = false"
                                   @if($active) aria-current="page" @endif
                                   data-label="{{ $item['label'] }}"
                                   :title="collapsed ? $el.dataset.label : null"
                                   :class="collapsed ? 'justify-center px-2' : ''"
                                   class="group relative flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-[13.5px] font-medium leading-tight transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1a8a8e]/40 {{ $active ? 'bg-rose-50/75 font-semibold text-rose-900' : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900' }}">
                                    @if($active)<span class="absolute inset-y-1.5 left-0 w-[2.5px] rounded-full bg-rose-700/85"></span>@endif
                                    <svg class="w-5 h-5 shrink-0 {{ $active ? 'text-rose-800' : 'text-slate-400 group-hover:text-slate-600' }}"
                                         fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                                    </svg>
                                    <span class="truncate" x-show="!collapsed">{{ $item['label'] }}</span>
                                    <span class="ml-auto flex items-center gap-1.5" x-show="!collapsed">
                                        @if(($item['badge'] ?? 0) > 0)
                                            <span class="rounded-md bg-rose-700 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-white">{{ $item['badge'] }}</span>
                                        @elseif($active)
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-700/85"></span>
                                        @endif
                                        @if(!empty($item['shortcut']))
                                            <kbd class="ui-kbd opacity-0 transition group-hover:opacity-100"
                                                  :class="altHeld ? 'opacity-100' : ''">{{ $item['shortcut'] }}</kbd>
                                        @endif
                                    </span>
                                </a>

                                @if(!empty($item['children']))
                                    <ul class="ml-5 mt-1 space-y-0.5 border-l border-slate-200 pl-3" x-show="!collapsed">
                                        @foreach($item['children'] as $child)
                                            @php $childActive = request()->routeIs(...$child['active']); @endphp
                                            <li>
                                                <a href="{{ route($child['route']) }}" @click="sidebarOpen = false"
                                                   @if($childActive) aria-current="page" @endif
                                                   class="group flex items-center gap-3 rounded-lg px-3 py-1.5 text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1a8a8e]/40 {{ $childActive ? 'bg-rose-50/75 font-semibold text-rose-900' : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900' }}">
                                                    <svg class="w-5 h-5 shrink-0 {{ $childActive ? 'text-rose-800' : 'text-slate-400 group-hover:text-slate-600' }}"
                                                         fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $child['icon'] }}"/>
                                                    </svg>
                                                    <span class="truncate">{{ $child['label'] }}</span>
                                                    @if($childActive)
                                                        <span class="ml-auto h-1.5 w-1.5 rounded-full bg-rose-700/85"></span>
                                                    @endif
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="border-t border-slate-200/70 p-2.5">
            <a href="{{ route('profile.edit') }}"
               data-label="Profile Settings"
               :title="collapsed ? $el.dataset.label : null"
               :class="collapsed ? 'justify-center px-2' : ''"
               class="group mb-1.5 flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-[13.5px] font-medium leading-tight text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1a8a8e]/40">
                <svg class="w-5 h-5 shrink-0 text-slate-400 group-hover:text-slate-600" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 100-8 4 4 0 000 8zm0 2c-3.3 0-6 1.8-6 4v2h12v-2c0-2.2-2.7-4-6-4z"/>
                </svg>
                <span class="truncate" x-show="!collapsed">Profile Settings</span>
            </a>

            <button type="button" @click="toggleCollapsed()"
                    :aria-pressed="collapsed"
                    :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                    :title="collapsed ? 'Expand sidebar (Alt + [)' : 'Collapse sidebar (Alt + [)'"
                    :class="collapsed ? 'justify-center px-2' : ''"
                    class="group mb-1.5 hidden w-full items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-[13px] font-medium text-slate-500 transition hover:bg-slate-50 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1a8a8e]/40 lg:flex">
                <svg class="w-5 h-5 shrink-0 transition-transform duration-200" :class="collapsed ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 19-7-7 7-7"/>
                </svg>
                <span x-show="!collapsed">Collapse sidebar</span>
                <kbd x-show="!collapsed" class="ui-kbd ml-auto">Alt [</kbd>
            </button>

            <div class="flex items-center gap-2.5 rounded-lg border border-slate-200/80 bg-slate-50 p-2" :class="collapsed ? 'justify-center' : ''">
                <span class="grad flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white">{{ $userInitials }}</span>
                <span class="min-w-0 leading-tight" x-show="!collapsed">
                    <span class="block truncate text-[13px] font-semibold text-slate-900">{{ $userName }}</span>
                    <span class="block truncate text-[11px] text-slate-500">{{ $currentUser?->email ?? 'Marian TBI team' }}</span>
                </span>
            </div>
        </div>
    </aside>

<div class="transition-[padding] duration-200" :class="collapsed ? 'lg:pl-[72px]' : 'lg:pl-[272px]'">
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
                            class="inline-flex items-center gap-1.5 rounded-lg bg-[#9E2A5B] px-3.5 py-2 text-sm font-semibold text-white shadow-2xs transition hover:bg-[#86224C] active:scale-[.98]">
                        <span class="text-base leading-none" aria-hidden="true">+</span> Add Incubatee
                    </button>
                    <button type="button" @click="$dispatch('open-contact-form')"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-slate-200 px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 active:scale-[.98]">
                        <span class="text-base leading-none text-teal-700" aria-hidden="true">+</span> Register Contact
                    </button>
                @endif

                <div class="relative" @click.outside="profileOpen = false">
                    <button type="button" @click="profileOpen = !profileOpen" :aria-expanded="profileOpen"
                            aria-label="Open user profile menu"
                            class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white p-1.5 pr-2.5 transition hover:bg-slate-50">
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

    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <header class="mb-6 flex flex-col gap-4 border-b border-slate-200/80 pb-5 md:flex-row md:items-end md:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-medium text-slate-400">@yield('eyebrow', 'Marian TBI Connect')</p>
                <h1 class="mt-1 text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">@yield('heading', 'Dashboard')</h1>
                <div class="mt-1 max-w-prose text-sm leading-relaxed text-slate-500">@yield('subheading')</div>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2.5">@yield('banner-actions')</div>
        </header>

        @if (session('success'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-4 text-sm text-green-800" role="alert">
                <span class="font-medium">Success:</span> {{ session('success') }}
            </div>
        @endif
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-4 text-sm text-green-800" role="alert">
                <span class="font-medium">Status:</span> {{ session('status') }}
            </div>
        @endif
        @if (session('warning'))
            <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800" role="alert">
                <span class="font-medium">Warning:</span> {{ session('warning') }}
            </div>
        @endif
        @if (session('drive_sync_warning'))
            <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800" role="alert">
                <span class="font-medium">Warning:</span> {{ session('drive_sync_warning') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800" role="alert">
                <span class="font-medium">Error:</span> {{ session('error') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800" role="alert">
                <span class="font-medium">There were errors with your submission:</span>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>
</div>

@include('partials.contact-slideover')
@stack('scripts')
</body>
</html>
