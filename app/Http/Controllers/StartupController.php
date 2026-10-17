<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveStartupRequest;
use App\Models\Contact;
use App\Models\Startup;
use Illuminate\Http\RedirectResponse;

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
        $attributes = $this->attributes($request);
        $founderContact = $this->registerFounderContact($attributes);
        $startup->update($attributes + ['founder_contact_id' => $founderContact->id]);

        return to_route('incubatees')->with('startup_status', $startup->name.' was updated.');
    }

    public function destroy(Startup $startup): RedirectResponse
    {
        $startup->forceFill([
            'archived_by' => request()->user()->id,
            'archive_reason' => null,
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
