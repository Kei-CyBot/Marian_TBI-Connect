<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Event;
use App\Models\Startup;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventsController extends Controller
{
    public function index(): View
    {
        $broadcastContacts = Contact::query()
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'side']);

        $events = Event::query()
            ->orderBy('starts_at')
            ->get()
            ->map(static fn (Event $event): array => [
                'id' => $event->id,
                'title' => $event->title,
                'scope' => $event->scope,
                'kind' => $event->kind,
                'date' => $event->starts_at->format('Y-m-d'),
                'time' => $event->starts_at->format('g:i A'),
                'startsLabel' => $event->starts_at->format('g:i A - M d, Y'),
                'endsLabel' => $event->ends_at->format('g:i A - M d, Y'),
                'loc' => $event->location,
                'virtual' => $event->virtual,
                'host' => $event->host,
                'rsvp' => [0, 0, 0],
            ])
            ->all();

        return view('events', [
            'events' => $events,
            'broadcastContacts' => $broadcastContacts,
            'mailConfigured' => ! in_array(config('mail.default'), ['log', 'array'], true),
        ]);
    }

    public function create(): View
    {
        return view('events.create', [
            'hosts' => $this->hosts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $event = Event::query()->create($this->attributes($request));

        return to_route('events.show', $event)->with('event_status', 'Event created.');
    }

    public function show(Event $event): View
    {
        return view('events.show', [
            'event' => $event,
            'invitations' => $event->invitations()->with('contact')->latest()->get(),
            'contacts' => Contact::query()
                ->whereNotNull('email')
                ->where('email', '<>', '')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'side']),
        ]);
    }

    public function edit(Event $event): View
    {
        return view('events.edit', [
            'event' => $event,
            'hosts' => $this->hosts(),
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $event->update($this->attributes($request));

        return to_route('events.show', $event)->with('event_status', 'Event updated.');
    }

    private function hosts(): array
    {
        return Startup::query()->orderBy('name')->pluck('name')->all();
    }

    private function attributes(Request $request): array
    {
        $request->merge([
            'kind' => $request->input('kind') === '__other__'
                ? $request->input('kind_other')
                : $request->input('kind'),
            'host' => $request->input('host') === '__other__'
                ? $request->input('host_other')
                : $request->input('host'),
            'virtual' => $request->boolean('virtual'),
        ]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'scope' => ['required', Rule::in(['internal', 'external', 'hybrid'])],
            'kind' => ['required', 'string', 'max:120'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'location' => ['required', 'string', 'max:255'],
            'virtual' => ['boolean'],
            'host' => ['required', 'string', 'max:255'],
        ]);

        return [
            ...$validated,
            'starts_at' => Carbon::parse($validated['starts_at']),
            'ends_at' => Carbon::parse($validated['ends_at']),
        ];
    }
}
