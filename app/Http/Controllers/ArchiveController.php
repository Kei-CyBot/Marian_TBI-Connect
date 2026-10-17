<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Startup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ArchiveController extends Controller
{
    public function index(): View
    {
        $startups = Startup::onlyTrashed()->with('archiver')->orderByDesc('deleted_at')->get();
        $contacts = Contact::onlyTrashed()->with('archiver')->orderByDesc('deleted_at')->get();

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

        return view('archives', compact('items'));
    }

    public function restoreStartup(string $startup): RedirectResponse
    {
        $record = Startup::onlyTrashed()->findOrFail($startup);
        $record->restore();
        $record->forceFill(['archived_by' => null])->save();

        return to_route('archives')->with('archive_status', $record->name.' was restored.');
    }

    public function restoreContact(string $contact): RedirectResponse
    {
        $record = Contact::onlyTrashed()->findOrFail($contact);
        $record->restore();
        $record->forceFill(['archived_by' => null])->save();

        return to_route('archives')->with('archive_status', $record->name.' was restored.');
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

        return to_route('archives')->with('archive_status', $record->name.' was permanently deleted.');
    }

    public function destroyContact(string $contact): RedirectResponse
    {
        $record = Contact::onlyTrashed()->findOrFail($contact);
        $name = $record->name;
        $record->forceDelete();

        return to_route('archives')->with('archive_status', $name.' was permanently deleted.');
    }
}
