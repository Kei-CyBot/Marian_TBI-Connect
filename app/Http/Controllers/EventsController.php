<?php

namespace App\Http\Controllers;

use App\Mail\EventCancelledMail;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Startup;
use App\Services\ActivityLogger;
use App\Support\GoogleDriveService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

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
            ->active()
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
                'status' => $event->status,
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

    public function markCompleted(Request $request, Event $event): RedirectResponse
    {
        abort_if($event->status !== 'scheduled', 422, 'Only scheduled events can be completed.');

        $validated = $request->validate([
            'actual_attendees_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'post_event_notes' => ['nullable', 'string', 'max:5000'],
            'post_event_document' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:10240'],
        ]);

        $document = $request->file('post_event_document');
        $path = $document?->store('events/'.$event->id.'/documents', 'local');
        if ($path === false) {
            throw new RuntimeException('The post-event document could not be stored.');
        }

        try {
            $event->update([
                'status' => 'completed',
                'completed_at' => now(),
                'archived_at' => now(),
                'actual_attendees_count' => $validated['actual_attendees_count'] ?? null,
                'post_event_notes' => $validated['post_event_notes'] ?? null,
                'post_event_document_path' => $path ?? $event->post_event_document_path,
                'post_event_document_original_name' => $document?->getClientOriginalName()
                    ?? $event->post_event_document_original_name,
            ]);
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        $driveWarning = null;
        if ($document !== null && app(GoogleDriveService::class)->isConnected($request->user())) {
            try {
                app(GoogleDriveService::class)->uploadEventDocument($event, $request->user());
            } catch (Throwable $exception) {
                app(GoogleDriveService::class)->logSyncFailure($exception, ['event_id' => $event->getKey()]);
                $driveWarning = 'File saved locally. Cloud backup will retry shortly.';
            }
        }

        ActivityLogger::log(
            'event_completed',
            "Event '{$event->title}' completed and moved to Archives.",
            $event,
            [
                'status' => 'completed',
                'actual_attendees_count' => $event->actual_attendees_count,
            ]
        );

        $redirect = to_route('events.show', $event)->with('event_status', 'Event marked as completed.');

        return $driveWarning === null
            ? $redirect
            : $redirect->with('drive_sync_warning', $driveWarning);
    }

    public function cancel(Request $request, Event $event): RedirectResponse
    {
        abort_if($event->status !== 'scheduled', 422, 'Only scheduled events can be cancelled.');

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:1000'],
            'send_cancellation_email' => ['nullable', 'boolean'],
        ]);

        $event->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'archived_at' => now(),
            'cancellation_reason' => $validated['cancellation_reason'],
        ]);

        if ($request->boolean('send_cancellation_email')) {
            foreach ($event->invitations()->whereIn('status', ['pending', 'attending'])->get() as $invitation) {
                try {
                    Mail::to($invitation->email)->send(new EventCancelledMail($event, $invitation));
                } catch (Throwable $exception) {
                    Log::error("Failed to send event cancellation email to {$invitation->email}: ".$exception->getMessage());
                }
            }
        }

        ActivityLogger::log(
            'event_cancelled',
            "Event '{$event->title}' was cancelled and archived. Reason: {$validated['cancellation_reason']}",
            $event,
            ['status' => 'cancelled', 'cancellation_reason' => $event->cancellation_reason]
        );

        return to_route('events.show', $event)->with('event_status', 'Event cancelled.');
    }

    public function archive(Event $event): RedirectResponse
    {
        $event->update(['archived_at' => now()]);

        ActivityLogger::log('event_archived', "Event '{$event->title}' was archived.", $event);

        return to_route('archives.index', ['tab' => 'events'])->with('archive_status', 'Event archived.');
    }

    public function restore(Event $event): RedirectResponse
    {
        $event->update([
            'archived_at' => null,
            'status' => 'scheduled',
        ]);

        ActivityLogger::log('event_restored', "Event '{$event->title}' was restored from Archives.", $event);

        return to_route('archives.index', ['tab' => 'events'])->with('archive_status', 'Event restored.');
    }

    public function downloadPostEventDocument(Event $event)
    {
        $path = $event->post_event_document_path;
        abort_unless($path, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return response()->download(
            $disk->path($path),
            $event->post_event_document_original_name ?: basename($path)
        );
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
