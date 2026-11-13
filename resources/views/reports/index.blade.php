@extends('layouts.app')
@section('title','Dashboard Reports')
@section('eyebrow','Reporting')
@section('heading','Dashboard Reports')
@section('subheading','Consolidated audit of uploaded documents, technical needs, and resource allocations.')
@section('banner-actions')
    <a href="{{ route('reports.export-csv') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-2xs transition hover:bg-slate-50">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-slate-400" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" /></svg>
        Export to CSV
    </a>
@endsection

@section('content')
@php
    $totalNeeds = $technicalNeeds->count();
    $pendingNeeds = $technicalNeeds->where('status', 'pending')->count();
    $approvedNeeds = $technicalNeeds->where('status', 'approved')->count();
@endphp

<div class="space-y-5">
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <section class="ui-card p-5">
            <p class="text-xs font-medium text-slate-400">Total Uploaded Files</p>
            <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-[#146e72]">{{ number_format($technicalNeedFiles + $repositoryFiles) }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ number_format($technicalNeedFiles) }} technical needs · {{ number_format($repositoryFiles) }} repository documents</p>
        </section>
        <section class="ui-card p-5">
            <p class="text-xs font-medium text-slate-400">Technical Needs</p>
            <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-[#b92369]">{{ number_format($totalNeeds) }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ number_format($pendingNeeds) }} pending · {{ number_format($approvedNeeds) }} approved</p>
        </section>
        <section class="ui-card p-5">
            <p class="text-xs font-medium text-slate-400">Allocated Items</p>
            <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-slate-900">{{ number_format($allocatedItems) }}</p>
            <p class="mt-2 text-xs text-slate-500">Resources and tools assigned to incubatees</p>
        </section>
        <section class="ui-card p-5">
            <p class="text-xs font-medium text-slate-400">Estimated Cost</p>
            <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-[#146e72]">₱{{ number_format($estimatedCost, 2) }}</p>
            <p class="mt-2 text-xs text-slate-500">Low {{ number_format($priorities['Low']) }} · Moderate {{ number_format($priorities['Moderate']) }} · High {{ number_format($priorities['High']) }}</p>
        </section>
    </div>

    <div class="ui-card overflow-hidden">
        <div class="border-b border-slate-200/80 px-5 py-4">
            <h2 class="font-semibold text-slate-900">Uploaded Files &amp; Document Audit Log</h2>
            <p class="text-xs text-slate-500">Technical need attachments and form repository documents.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Source</th>
                        <th>Startup / Submitter</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($auditRows as $row)
                        <tr>
                            <td class="font-medium text-slate-900">{{ $row['document'] }}</td>
                            <td>
                                <span class="ui-badge bg-slate-50 uppercase tracking-wide text-slate-500 ring-slate-200">{{ $row['source'] }}</span>
                            </td>
                            <td>{{ $row['owner'] }}</td>
                            <td>{{ $row['status'] }}</td>
                            <td class="tabular-nums">{{ $row['updated_at'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500">No uploaded files or documents recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="ui-card overflow-hidden">
        <div class="border-b border-slate-200/80 px-5 py-4">
            <h2 class="font-semibold text-slate-900">Technical Needs &amp; Resource Allocations Report</h2>
            <p class="text-xs text-slate-500">Priority, status, ownership, and estimated cost per requirement.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th>Startup / Incubatee</th>
                        <th>Requirement</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Responsible</th>
                        <th>Resource / Tool</th>
                        <th class="text-right">Estimated Cost</th>
                        <th>Date Entry</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($technicalNeeds as $need)
                        <tr>
                            <td class="font-medium text-slate-900">{{ $need->startup?->name ?? '—' }}</td>
                            <td>{{ $need->requirement }}</td>
                            <td>
                                <span @class([
                                    'ui-badge',
                                    'bg-rose-50 text-rose-700 ring-rose-200' => $need->priority === 'High',
                                    'bg-amber-50 text-amber-700 ring-amber-200' => $need->priority === 'Moderate',
                                    'bg-teal-50 text-teal-700 ring-teal-200' => $need->priority === 'Low',
                                    'bg-slate-50 text-slate-500 ring-slate-200' => ! in_array($need->priority, ['High', 'Moderate', 'Low'], true),
                                ])><span class="ui-badge-dot"></span>{{ $need->priority }}</span>
                            </td>
                            <td>{{ ucfirst((string) $need->status) }}</td>
                            <td>{{ $need->responsible_team_member ?: '—' }}</td>
                            <td>{{ $need->resource_tool ?: '—' }}</td>
                            <td class="text-right tabular-nums">{{ $need->subscription_price !== null ? '₱'.number_format((float) $need->subscription_price, 2) : '—' }}</td>
                            <td class="tabular-nums">{{ $need->date_entry?->format('M j, Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-sm text-slate-500">No technical needs recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
