<div
    x-data="{
        open: @js($errors->any() && old('startup_id') !== null),
        startupId: @js(old('startup_id', '')),
        startupName: @js(old('startup_name', '')),
        areas: @js($assessmentAreas),
        defaults: @js($assessmentDefaults),
        savedAssessments: @js($assessments),
        ratings: @js(old('data.competencyRatings', $assessmentDefaults)),
        skills: @js(old('data.otherCriticalSkills', ['', '', '', '', ''])),
        get totalPoints(){ return Object.values(this.ratings).reduce((total, rating) => total + Number(rating || 0), 0) },
        openFor(startup){
            this.startupId=String(startup.id);
            this.startupName=startup.name;
            const saved=this.savedAssessments[this.startupId];
            this.ratings={...this.defaults,...(saved?.ratings || {})};
            this.skills=[...(saved?.otherCriticalSkills || []), '', '', '', '', ''].slice(0,5);
            this.open=true;
        },
        close(){ this.open=false }
    }"
    @open-startup-assessment.window="openFor($event.detail)"
    @keydown.escape.window="close()"
>
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-2 backdrop-blur-sm sm:p-4 lg:p-6" @click.self="close()">
        <form method="POST" action="{{ route('startup-assessments.store') }}" class="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-white/70 bg-white shadow-2xl">
            @csrf
            <input type="hidden" name="startup_id" :value="startupId">
            <input type="hidden" name="startup_name" :value="startupName">
            <input type="hidden" name="data" :value="JSON.stringify({competencyRatings:ratings,otherCriticalSkills:skills})">

            <header class="grad flex items-start justify-between gap-4 px-5 py-4 text-white sm:px-7 sm:py-5">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-white/75">Incubatee training needs</p>
                    <h2 class="mt-1 text-xl font-bold sm:text-2xl">Startup Assessment Form</h2>
                    <p class="mt-1 truncate text-sm text-white/85" x-text="startupName"></p>
                </div>
                <button type="button" @click="close()" aria-label="Close assessment" class="shrink-0 rounded-xl p-2 text-white/80 transition hover:bg-white/15 hover:text-white">✕</button>
            </header>

            <div class="flex-1 space-y-5 overflow-y-auto bg-slate-50/70 p-4 sm:space-y-6 sm:p-6 lg:p-7">
                @if ($errors->any() && old('startup_id') !== null)
                    <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-sm">{{ $errors->first() }}</div>
                @endif
                <section class="rounded-2xl border border-[#b92369]/10 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Rate current experience</p>
                            <p class="mt-0.5 text-xs text-slate-500">Use the scale below to identify future training needs.</p>
                        </div>
                        <span class="rounded-full bg-[#fcf0f5] px-3 py-1 text-xs font-semibold text-[#b92369]">1–5 rating scale</span>
                    </div>
                    <div class="grid gap-2 text-xs text-slate-600 sm:grid-cols-2 lg:grid-cols-5">
                        <p class="rounded-lg bg-slate-50 px-3 py-2"><strong class="text-[#b92369]">5 · Excellent</strong><br>Expert; can mentor others.</p>
                        <p class="rounded-lg bg-slate-50 px-3 py-2"><strong class="text-slate-800">4 · Satisfactory</strong><br>Proficient; works independently.</p>
                        <p class="rounded-lg bg-slate-50 px-3 py-2"><strong class="text-slate-800">3 · Good</strong><br>Basic competence; occasional guidance.</p>
                        <p class="rounded-lg bg-slate-50 px-3 py-2"><strong class="text-slate-800">2 · Moderate</strong><br>Some exposure; needs guidance.</p>
                        <p class="rounded-lg bg-slate-50 px-3 py-2"><strong class="text-slate-800">1 · Poor</strong><br>No experience or knowledge.</p>
                    </div>
                </section>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 sm:px-5">
                        <h3 class="text-sm font-semibold text-slate-800">Future Training Areas / Competencies</h3>
                        <span class="text-xs text-slate-500">Choose one rating for each competency</span>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px]">
                        <thead class="border-b border-slate-200 bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Competency</th>
                                <th class="w-48 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Rating</th>
                            </tr>
                        </thead>
                        <template x-for="area in areas" :key="area.id">
                            <tbody class="divide-y divide-gray-100">
                                <tr class="bg-[#fcf0f5]/65"><th colspan="2" class="border-l-2 border-[#b92369] px-4 py-3 text-left text-sm font-semibold text-[#8f1c53]" x-text="area.title"></th></tr>
                                <template x-for="(competency,index) in area.competencies" :key="competency.key">
                                    <tr class="transition hover:bg-slate-50">
                                        <td class="px-4 py-3.5 text-sm text-slate-700"><span class="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-[11px] font-semibold text-slate-500" x-text="index+1"></span><span x-text="competency.label"></span></td>
                                        <td class="px-4 py-3">
                                            <select x-model.number="ratings[competency.key]" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm transition focus:border-[#b92369] focus:outline-none focus:ring-2 focus:ring-[#b92369]/15">
                                                <option value="5">5 - Excellent</option>
                                                <option value="4">4 - Satisfactory</option>
                                                <option value="3">3 - Good</option>
                                                <option value="2">2 - Moderate</option>
                                                <option value="1">1 - Poor</option>
                                            </select>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </template>
                        <tfoot class="border-t border-gray-200 bg-gray-50">
                            <tr>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Total Points</td>
                                <td class="px-4 py-3"><span class="inline-flex min-w-16 items-center justify-center rounded-lg bg-[#fcf0f5] px-3 py-1.5 text-sm font-bold text-[#b92369]" x-text="totalPoints"></span></td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                </div>

                <section class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-800">Other critical skills or training</h3>
                        <p class="mt-1 text-xs text-slate-500">List other skills or training not mentioned that are important to your venture.</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <template x-for="(skill,index) in skills" :key="index">
                            <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2 focus-within:border-[#1a8a8e] focus-within:ring-2 focus-within:ring-[#1a8a8e]/10">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#eaf6f6] text-xs font-semibold text-[#1a8a8e]" x-text="index+1"></span>
                                <input x-model="skills[index]" type="text" :aria-label="'Other critical skill '+(index+1)" maxlength="255" placeholder="Enter a skill or training need" class="min-w-0 flex-1 border-0 bg-transparent p-1 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:ring-0">
                            </label>
                        </template>
                    </div>
                </section>
            </div>

            <footer class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:py-4">
                <p class="hidden text-xs text-slate-500 sm:block">Your assessment is saved to this incubatee’s profile.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="close()" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Close</button>
                    <button type="submit" class="grad rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-[#b92369]/30 focus:ring-offset-2">Save Assessment</button>
                </div>
            </footer>
        </form>
    </div>
</div>
