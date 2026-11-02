@extends('layouts.app')
@section('title','Edit Event')
@section('eyebrow','Events & Outreach')
@section('heading','Edit Event')
@section('subheading','Update the schedule, host, and location for this event.')
@section('banner-actions')
    <a href="{{ route('events.show', $event) }}" class="rounded-xl bg-white/15 px-4 py-2 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/25">View event</a>
@endsection

@section('content')
<div class="mx-auto max-w-2xl rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
    @include('events._form', [
        'action' => route('events.update', $event),
        'method' => 'PUT',
        'submitLabel' => 'Save changes',
    ])
</div>
@endsection
