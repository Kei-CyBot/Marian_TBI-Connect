<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveStartupRequest;
use App\Models\Contact;
use App\Models\FormSubmission;
use App\Models\Startup;
use App\Models\StartupDriveLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StartupController extends Controller
{
    public function store(SaveStartupRequest $request): RedirectResponse
    {
        $attributes = $this->attributes($request);
        $founderContact = $this->registerFounderContact($attributes);
        $startup = Startup::query()->create($attributes + [
            'founder_contact_id' => $founderContact->id,
            'folder_access_manager_id' => $request->user()->id,
        ]);

        return to_route('incubatees')->with('startup_status', $startup->name.' was added.');
    }

    public function update(SaveStartupRequest $request, Startup $startup): RedirectResponse
    {
        $originalName = $startup->name;
        $attributes = $this->attributes($request);
        $founderContact = $this->registerFounderContact($attributes);

        DB::transaction(function () use ($startup, $attributes, $founderContact, $originalName): void {
            $startup->update($attributes + ['founder_contact_id' => $founderContact->id]);

            if ($startup->name !== $originalName) {
                FormSubmission::query()
                    ->where('startup_name', $originalName)
                    ->update(['startup_name' => $startup->name]);

                StartupDriveLink::query()
                    ->where('startup_name', $originalName)
                    ->update(['startup_name' => $startup->name]);
            }
        });

        return to_route('incubatees')->with('startup_status', $startup->name.' was updated.');
    }

    public function destroy(Request $request, Startup $startup): RedirectResponse
    {
        $validated = $request->validate([
            'archive_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $startup->forceFill([
            'archived_by' => $request->user()->id,
            'archive_reason' => $validated['archive_reason'] ?? null,
        ])->save();
        $startup->delete();

        return to_route('incubatees')->with('startup_status', $startup->name.' was archived. View or restore it from Archives.');
    }

    private function attributes(SaveStartupRequest $request): array
    {
        $validated = $request->validated();

        return [
            'name' => $validated['name'],
            'founder' => $validated['founder'],
            'contact_email' => $validated['email'],
            'track' => $validated['track'],
            'venture_type' => $validated['venture_type'] ?? null,
            'status' => $validated['status'],
            'cohort_id' => $validated['cohort_id'],
            'campus_department' => $validated['track'] === 'internal' ? ($validated['campus_department'] ?? null) : null,
            'legal_entity_type' => $validated['track'] === 'external' ? ($validated['legal_entity_type'] ?? null) : null,
        ];
    }

    private function registerFounderContact(array $attributes): Contact
    {
        $email = mb_strtolower($attributes['contact_email']);
        $contact = Contact::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($contact) {
            return $contact;
        }

        return Contact::query()->create([
            'side' => $attributes['track'],
            'name' => $attributes['founder'],
            'email' => $attributes['contact_email'],
            'affiliation_type' => 'Founder',
            'role' => 'Founder',
            'organization' => $attributes['name'],
            'domains' => [],
            'technical_skills' => [],
            'nda_status' => 'Pending',
            'verification_status' => 'Pending Verification',
            'incubatees' => [],
            'activity_log' => [],
        ]);
    }
}
