@extends('layouts.app')
@section('title','Create Event')
@section('eyebrow','Events & Outreach')
@section('heading','Create Event')
@section('subheading','Schedule an event and define who hosts it. Times are saved exactly as entered.')
@section('banner-actions')
    <a href="{{ route('events') }}" class="rounded-xl bg-white/15 px-4 py-2 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/25">Back to events</a>
@endsection

@section('content')
<div class="mx-auto max-w-2xl rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
    @include('events._form', [
        'action' => route('events.store'),
        'method' => 'POST',
        'submitLabel' => 'Create event',
    ])
</div>
@endsection
