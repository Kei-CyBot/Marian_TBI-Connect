<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Event;
use App\Models\Startup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ArchiveController extends Controller
{
    public function index(Request $request): View
    {
        $startups = Startup::onlyTrashed()->with('archiver')->orderByDesc('deleted_at')->get();
        $contacts = Contact::onlyTrashed()->with('archiver')->orderByDesc('deleted_at')->get();

        $events = Event::query()
            ->archived()
            ->withCount('invitations')
            ->orderByDesc('starts_at')
            ->get()
            ->map(static fn (Event $event): array => [
                'id' => $event->id,
                'title' => $event->title,
                'status' => $event->status,
                'statusLabel' => ucfirst($event->status),
                'date' => $event->starts_at?->format('M j, Y'),
                'turnout' => $event->actual_attendees_count !== null && $event->invitations_count > 0
                    ? (int) round($event->actual_attendees_count / $event->invitations_count * 100)
                    : null,
                'reason' => $event->cancellation_reason ?: '—',
                'notes' => $event->post_event_notes ? Str::limit($event->post_event_notes, 120) : '—',
                'archivedAt' => ($event->archived_at ?? $event->completed_at ?? $event->cancelled_at)?->format('M j, Y'),
                'summaryUrl' => route('events.show', $event),
                'documentUrl' => $event->post_event_document_path
                    ? route('events.post-event-document', $event)
                    : null,
                'restoreUrl' => route('events.restore', $event),
            ]);

        $initialTab = match ($request->query('tab')) {
            'events' => 'Events',
            'incubatees' => 'Incubatees',
            'contacts' => 'Contacts',
            default => 'All',
        };

        $items = $startups->map(fn (Startup $startup): array => [
            'id' => $startup->id,
            'kind' => 'Incubatee',
            'name' => $startup->name,
            'track' => $startup->track,
            'archivedAt' => $startup->deleted_at?->format('M j, Y'),
            'timestamp' => $startup->deleted_at?->timestamp,
            'archivedBy' => $startup->archiver?->name ?? 'Unknown user',
            'reason' => $startup->archive_reason ?: 'No reason provided',
            'restoreUrl' => route('archives.startups.restore', $startup),
            'deleteUrl' => route('archives.startups.destroy', $startup),
        ])->concat($contacts->map(fn (Contact $contact): array => [
            'id' => $contact->id,
            'kind' => 'Contact',
            'name' => $contact->name,
            'track' => $contact->side,
            'archivedAt' => $contact->deleted_at?->format('M j, Y'),
            'timestamp' => $contact->deleted_at?->timestamp,
            'archivedBy' => $contact->archiver?->name ?? 'Unknown user',
            'reason' => $contact->archive_reason ?: 'No reason provided',
            'restoreUrl' => route('archives.contacts.restore', $contact),
            'deleteUrl' => route('archives.contacts.destroy', $contact),
        ]))->sortByDesc('timestamp')->values();

        return view('archives', compact('items', 'events', 'initialTab'));
    }

    public function restoreStartup(string $startup): RedirectResponse
    {
        $record = Startup::onlyTrashed()->findOrFail($startup);
        $record->restore();
        $record->forceFill(['archived_by' => null])->save();

        return to_route('archives.index')->with('archive_status', $record->name.' was restored.');
    }

    public function restoreContact(string $contact): RedirectResponse
    {
        $record = Contact::onlyTrashed()->findOrFail($contact);
        $record->restore();
        $record->forceFill(['archived_by' => null])->save();

        return to_route('archives.index')->with('archive_status', $record->name.' was restored.');
    }

    public function destroyStartup(string $startup): RedirectResponse
    {
        $record = Startup::onlyTrashed()->findOrFail($startup);

        DB::transaction(function () use ($record): void {
            DB::table('startup_assessments')
                ->where('startup_id', $record->id)
                ->delete();
            DB::table('startup_drive_links')
                ->where('startup_id', $record->id)
                ->delete();
            $record->forceDelete();
        });

        return to_route('archives.index')->with('archive_status', $record->name.' was permanently deleted.');
    }

    public function destroyContact(string $contact): RedirectResponse
    {
        $record = Contact::onlyTrashed()->findOrFail($contact);
        $name = $record->name;
        $record->forceDelete();

        return to_route('archives.index')->with('archive_status', $name.' was permanently deleted.');
    }
}
