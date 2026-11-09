@extends('layouts.app')
@section('title','Dashboard Reports')
@section('eyebrow','Reporting')
@section('heading','Dashboard Reports')
@section('subheading','Consolidated audit of uploaded documents, technical needs, and resource allocations.')
@section('banner-actions')
    <a href="{{ route('reports.export-csv') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-[#146e72] shadow-sm hover:bg-[#eaf6f6]">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" /></svg>
        Export to CSV
    </a>
@endsection

@section('content')
@php
    $totalNeeds = $technicalNeeds->count();
    $pendingNeeds = $technicalNeeds->where('status', 'pending')->count();
    $approvedNeeds = $technicalNeeds->where('status', 'approved')->count();
@endphp

<div class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Uploaded Files</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-[#146e72]">{{ number_format($technicalNeedFiles + $repositoryFiles) }}</p>
            <p class="mt-2 text-xs text-gray-500">{{ number_format($technicalNeedFiles) }} technical needs · {{ number_format($repositoryFiles) }} repository documents</p>
        </section>
        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Technical Needs</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-[#b92369]">{{ number_format($totalNeeds) }}</p>
            <p class="mt-2 text-xs text-gray-500">{{ number_format($pendingNeeds) }} pending · {{ number_format($approvedNeeds) }} approved</p>
        </section>
        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Allocated Items</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ number_format($allocatedItems) }}</p>
            <p class="mt-2 text-xs text-gray-500">Resources and tools assigned to incubatees</p>
        </section>
        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Estimated Cost</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-[#146e72]">₱{{ number_format($estimatedCost, 2) }}</p>
            <p class="mt-2 text-xs text-gray-500">Low {{ number_format($priorities['Low']) }} · Moderate {{ number_format($priorities['Moderate']) }} · High {{ number_format($priorities['High']) }}</p>
        </section>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 px-5 py-4">
            <h2 class="font-semibold text-slate-900">Uploaded Files &amp; Document Audit Log</h2>
            <p class="text-xs text-gray-500">Technical need attachments and form repository documents.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                    <tr>
                        <th class="px-5 py-3">Document</th>
                        <th class="px-3 py-3">Source</th>
                        <th class="px-3 py-3">Startup / Submitter</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-5 py-3">Last Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($auditRows as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-medium text-slate-900">{{ $row['document'] }}</td>
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-gray-600">{{ $row['source'] }}</span>
                            </td>
                            <td class="px-3 py-3 text-gray-700">{{ $row['owner'] }}</td>
                            <td class="px-3 py-3 text-gray-700">{{ $row['status'] }}</td>
                            <td class="px-5 py-3 text-gray-700">{{ $row['updated_at'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-700">No uploaded files or documents recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 px-5 py-4">
            <h2 class="font-semibold text-slate-900">Technical Needs &amp; Resource Allocations Report</h2>
            <p class="text-xs text-gray-500">Priority, status, ownership, and estimated cost per requirement.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                    <tr>
                        <th class="px-5 py-3">Startup / Incubatee</th>
                        <th class="px-3 py-3">Requirement</th>
                        <th class="px-3 py-3">Priority</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3">Responsible</th>
                        <th class="px-3 py-3">Resource / Tool</th>
                        <th class="px-3 py-3">Estimated Cost</th>
                        <th class="px-5 py-3">Date Entry</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($technicalNeeds as $need)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-medium text-slate-900">{{ $need->startup?->name ?? '—' }}</td>
                            <td class="px-3 py-3 text-gray-700">{{ $need->requirement }}</td>
                            <td class="px-3 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide',
                                    'bg-rose-100 text-rose-700' => $need->priority === 'High',
                                    'bg-amber-100 text-amber-700' => $need->priority === 'Moderate',
                                    'bg-teal-100 text-teal-700' => $need->priority === 'Low',
                                    'bg-gray-100 text-gray-600' => ! in_array($need->priority, ['High', 'Moderate', 'Low'], true),
                                ])>{{ $need->priority }}</span>
                            </td>
                            <td class="px-3 py-3 text-gray-700">{{ ucfirst((string) $need->status) }}</td>
                            <td class="px-3 py-3 text-gray-700">{{ $need->responsible_team_member ?: '—' }}</td>
                            <td class="px-3 py-3 text-gray-700">{{ $need->resource_tool ?: '—' }}</td>
                            <td class="px-3 py-3 text-gray-700">{{ $need->subscription_price !== null ? '₱'.number_format((float) $need->subscription_price, 2) : '—' }}</td>
                            <td class="px-5 py-3 text-gray-700">{{ $need->date_entry?->format('M j, Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-sm text-gray-700">No technical needs recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
