<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Apply · Marian TBI Connect</title>
    <link rel="icon" type="image/png" href="{{ asset('marian-tbi-logo.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>body{font-family:Inter,ui-sans-serif,system-ui,sans-serif}[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-[#F8FAFC] text-[#0F172A] antialiased"
      x-data="{
          stream:@js(old('stream')),
          get c(){ return this.stream==='academic' ? '#b92369' : '#1a8a8e' },
          get tint(){ return this.stream==='academic' ? '#fcf0f5' : '#eaf6f6' },
          f:{name:'',founder:'',email:'',sector:'Agritech',pitch:'',dept:'',ip:'No',advisor:'',entity:'Inc.',stage:'Pre-Seed',website:'',employees:''}
      }">

    <header class="mx-auto flex max-w-3xl items-center gap-3 px-6 pt-8">
        <img src="{{ asset('marian-tbi-logo.png') }}" alt="" class="h-10 w-10 object-contain">
        <p class="font-bold">Marian TBI Connect</p>
    </header>

    <main class="mx-auto max-w-3xl px-6 py-8">
        @if (session('intake_status'))
            <div role="status" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ session('intake_status') }}</div>
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
            <p class="mt-1 text-sm text-white/85">Choose your stream so we can route your application to the right review team.</p>
        </section>

        {{-- Stream chooser --}}
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <button @click="stream='academic'" class="rounded-2xl border-2 bg-white p-5 text-left transition hover:shadow-md" :class="stream==='academic' ? 'border-[#b92369] bg-[#fcf0f5]' : 'border-gray-200'">
                <span class="rounded-lg bg-[#fcf0f5] px-2 py-0.5 text-xs font-semibold text-[#b92369]">Academic Stream</span>
                <p class="mt-2 font-semibold">Faculty, students &amp; researchers</p>
                <p class="text-sm text-gray-500">Spin-offs, campus labs, university IP.</p>
            </button>
            <button @click="stream='industry'" class="rounded-2xl border-2 bg-white p-5 text-left transition hover:shadow-md" :class="stream==='industry' ? 'border-[#1a8a8e] bg-[#eaf6f6]' : 'border-gray-200'">
                <span class="rounded-lg bg-[#eaf6f6] px-2 py-0.5 text-xs font-semibold text-[#1a8a8e]">Industry Stream</span>
                <p class="mt-2 font-semibold">Startups &amp; businesses</p>
                <p class="text-sm text-gray-500">Regional startups, corporate spin-outs.</p>
            </button>
        </div>

        {{-- Form --}}
        <form x-show="stream" x-cloak x-transition method="POST" action="{{ route('startup-form.store') }}" class="mt-6 space-y-4 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            @csrf
            <input type="hidden" name="stream" :value="stream">
            <div class="rounded-xl px-3 py-2 text-xs font-semibold" :style="`background:${tint};color:${c}`" x-text="stream==='academic' ? 'Academic Stream application' : 'Industry Stream application'"></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Startup / project name</label><input required name="name" x-model="f.name" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none" :style="`--c:${c}`" @focus="$el.style.borderColor=c" @blur="$el.style.borderColor=''"></div>
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Lead founder</label><input required name="founder" x-model="f.founder" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none"></div>
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Email</label><input required type="email" name="email" x-model="f.email" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none"></div>
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Industry sector</label>
                    <select name="sector" x-model="f.sector" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"><option>Agritech</option><option>Fintech</option><option>Healthtech</option><option>Edtech</option><option>AI/Deeptech</option><option>Clean Energy</option></select></div>
            </div>

            {{-- Academic-only --}}
            <div x-show="stream==='academic'" class="grid gap-4 sm:grid-cols-2">
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Campus department</label><input name="dept" x-model="f.dept" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Faculty advisor</label><input name="advisor" x-model="f.advisor" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
                <div class="sm:col-span-2"><label class="block mb-1 text-xs font-semibold text-slate-600">Uses university IP / research?</label>
                    <select name="ip" x-model="f.ip" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"><option>No</option><option>Yes — disclosure filed with TTO</option><option>Yes — disclosure not yet filed</option></select></div>
            </div>

            {{-- Industry-only --}}
            <div x-show="stream==='industry'" x-cloak class="grid gap-4 sm:grid-cols-2">
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Legal entity type</label>
                    <select name="entity" x-model="f.entity" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"><option>Inc.</option><option>LLC</option><option>Corp</option></select></div>
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Funding stage</label>
                    <select name="stage" x-model="f.stage" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"><option>Pre-Seed</option><option>Seed</option><option>Series A</option></select></div>
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Website</label><input name="website" x-model="f.website" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="https://"></div>
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Team size</label><input type="number" name="employees" x-model="f.employees" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
            </div>

            <div><label class="block mb-1 text-xs font-semibold text-slate-600">One-paragraph pitch</label><textarea rows="4" name="pitch" x-model="f.pitch" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea></div>
            <button class="w-full rounded-xl py-2.5 text-sm font-semibold text-white transition active:scale-[.99]" :style="`background:${c}`">Submit Application</button>
        </form>
    </main>
</body>
</html>
