<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Alumni Update · Marian TBI Connect</title>
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
          f:{name:'',startup:'',email:'',gradYear:'',status:'Operating',employees:'',funding:'',revenue:'',licensed:'No',notes:'',mentor:false}
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
            <p class="text-xs font-semibold uppercase tracking-wider text-white/70">Alumni Network</p>
            <h1 class="mt-1 text-2xl font-bold">Alumni Progress Update</h1>
            <p class="mt-1 text-sm text-white/85">Help us track post-incubation outcomes and keep you connected to the network.</p>
        </section>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <button @click="stream='academic'" class="rounded-2xl border-2 bg-white p-5 text-left transition hover:shadow-md" :class="stream==='academic' ? 'border-[#b92369] bg-[#fcf0f5]' : 'border-gray-200'">
                <span class="rounded-lg bg-[#fcf0f5] px-2 py-0.5 text-xs font-semibold text-[#b92369]">Academic Alumni</span><p class="mt-2 font-semibold">Spin-off / campus venture</p></button>
            <button @click="stream='industry'" class="rounded-2xl border-2 bg-white p-5 text-left transition hover:shadow-md" :class="stream==='industry' ? 'border-[#1a8a8e] bg-[#eaf6f6]' : 'border-gray-200'">
                <span class="rounded-lg bg-[#eaf6f6] px-2 py-0.5 text-xs font-semibold text-[#1a8a8e]">Industry Alumni</span><p class="mt-2 font-semibold">Commercial startup</p></button>
        </div>

        <form x-show="stream" x-cloak x-transition method="POST" action="{{ route('alumni-form.store') }}" class="mt-6 space-y-4 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            @csrf
            <input type="hidden" name="stream" :value="stream">
            <div class="rounded-xl px-3 py-2 text-xs font-semibold" :style="`background:${tint};color:${c}`" x-text="stream==='academic' ? 'Academic alumni update' : 'Industry alumni update'"></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Your name</label><input required name="name" x-model="f.name" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Startup</label><input required name="startup" x-model="f.startup" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Email</label><input required type="email" name="email" x-model="f.email" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Graduation year</label><input type="number" name="gradYear" x-model="f.gradYear" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Current status</label>
                    <select name="status" x-model="f.status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"><option>Operating</option><option>Acquired</option><option>Pivoted</option><option>Paused</option><option>Closed</option></select></div>
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Team size</label><input type="number" name="employees" x-model="f.employees" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
            </div>
            <div x-show="stream==='academic'" class="grid gap-4 sm:grid-cols-2">
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">University IP licensed?</label>
                    <select name="licensed" x-model="f.licensed" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"><option>No</option><option>Yes — active license</option><option>In negotiation</option></select></div>
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Grants secured since graduation</label><input name="funding" x-model="f.funding" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
            </div>
            <div x-show="stream==='industry'" x-cloak class="grid gap-4 sm:grid-cols-2">
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Funding raised since graduation</label><input name="funding" x-model="f.funding" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
                <div><label class="block mb-1 text-xs font-semibold text-slate-600">Annual revenue tier</label>
                    <select name="revenue" x-model="f.revenue" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"><option>&lt; $50k</option><option>$50k – $250k</option><option>$250k – $1M</option><option>$1M+</option></select></div>
            </div>
            <div><label class="block mb-1 text-xs font-semibold text-slate-600">Highlights &amp; milestones</label><textarea rows="3" name="notes" x-model="f.notes" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="mentor" value="1" x-model="f.mentor" class="rounded"> I'm open to mentoring current incubatees</label>
            <button class="w-full rounded-xl py-2.5 text-sm font-semibold text-white" :style="`background:${c}`">Submit Update</button>
        </form>
    </main>
</body>
</html>
