@extends('layouts.app')
@section('title','Activity Log')
@section('eyebrow','Administration')
@section('heading','Activity Log')
@section('subheading','Administrative actions on form submissions and technical needs.')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                        <tr>
                            <th class="px-5 py-3">Description</th>
                            <th class="px-3 py-3">Action</th>
                            <th class="px-3 py-3">User</th>
                            <th class="px-3 py-3">IP Address</th>
                            <th class="px-5 py-3">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($logs as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 text-slate-900">{{ $log->description }}</td>
                                <td class="px-3 py-3 capitalize text-gray-700">{{ $log->action }}</td>
                                <td class="px-3 py-3 text-gray-700">{{ $log->user?->name ?? 'System' }}</td>
                                <td class="px-3 py-3 text-gray-700">{{ $log->ip_address }}</td>
                                <td class="px-5 py-3 text-gray-700">{{ $log->created_at?->format('M j, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-700">No activity logged yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($logs->hasPages())
                <div class="border-t border-gray-200 px-5 py-3">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
