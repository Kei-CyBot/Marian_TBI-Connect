<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveContactRequest;
use App\Models\Contact;
use App\Models\ContactStartupAssignment;
use App\Models\Startup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(): View
    {
        $this->ensureSampleContacts();
        Startup::ensureConfigured();

        $contacts = Contact::query()
            ->with(['startupAssignments.startup', 'startupAssignments.confirmer'])
            ->orderBy('name')
            ->get()
            ->map(
                static fn (Contact $contact): array => [
                    'id' => $contact->id,
                    'side' => $contact->side,
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'affiliationType' => $contact->affiliation_type,
                    'role' => $contact->role,
                    'org' => $contact->organization,
                    'academicProgram' => $contact->academic_program,
                    'academicYear' => $contact->academic_year,
                    'domains' => $contact->domains ?? [],
                    'domainOther' => $contact->domain_other,
                    'technicalSkills' => $contact->technical_skills ?? [],
                    'technicalOther' => $contact->technical_other,
                    'skillDetails' => $contact->skill_details,
                    'nda' => $contact->nda_status,
                    'verify' => $contact->verification_status,
                    'incubatees' => $contact->startupAssignments
                        ->filter(static fn (ContactStartupAssignment $assignment): bool => $assignment->startup !== null && ! $assignment->startup->trashed())
                        ->map(static fn (ContactStartupAssignment $assignment): string => $assignment->startup->name)
                        ->values()
                        ->all(),
                    'assignments' => $contact->startupAssignments
                        ->filter(static fn (ContactStartupAssignment $assignment): bool => $assignment->startup !== null && ! $assignment->startup->trashed())
                        ->map(static fn (ContactStartupAssignment $assignment): array => [
                            'startupId' => $assignment->startup->id,
                            'startup' => $assignment->startup->name,
                            'founder' => $assignment->startup->founder,
                            'email' => $assignment->startup->contact_email,
                            'method' => $assignment->verification_method,
                            'notes' => $assignment->verification_notes,
                            'confirmedBy' => $assignment->confirmer?->name ?? 'Unknown user',
                            'confirmedAt' => $assignment->confirmed_at?->format('M j, Y'),
                        ])
                        ->values()
                        ->all(),
                    'log' => $contact->activity_log ?? [],
                ]
            )->all();

        $startupOptions = Startup::query()
            ->orderBy('name')
            ->get(['id', 'name', 'founder', 'contact_email'])
            ->map(static fn (Startup $startup): array => [
                'id' => $startup->id,
                'name' => $startup->name,
                'founder' => $startup->founder,
                'email' => $startup->contact_email,
            ])
            ->all();

        $broadcastContacts = Contact::query()
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('contacts', [
            'contacts' => $contacts,
            'startupOptions' => $startupOptions,
            'broadcastContacts' => $broadcastContacts,
            'mailConfigured' => ! in_array(config('mail.default'), ['log', 'array'], true),
        ]);
    }

    public function store(SaveContactRequest $request): RedirectResponse
    {
        Contact::query()->create($this->attributes($request) + [
            'verification_status' => 'Pending Verification',
            'incubatees' => [],
            'activity_log' => [],
        ]);

        return to_route('contacts')->with('contact_status', 'Contact added to the directory.');
    }

    public function update(SaveContactRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($this->attributes($request));

        return to_route('contacts')->with('contact_status', $contact->name.' was updated.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->forceFill([
            'archived_by' => request()->user()->id,
            'archive_reason' => null,
        ])->save();
        $contact->delete();

        return to_route('contacts')->with('contact_status', $contact->name.' was archived. View or restore it from Archives.');
    }

    public function assignStartup(Request $request, Contact $contact): RedirectResponse
    {
        $validated = $request->validate([
            'startup_id' => ['required', 'integer', Rule::exists('startups', 'id')->whereNull('deleted_at')],
            'verification_method' => ['required', Rule::in(['founder_email_match', 'program_records', 'authorized_confirmation'])],
            'verification_notes' => ['required', 'string', 'max:1000'],
        ]);
        $startup = Startup::query()->findOrFail($validated['startup_id']);

        if (
            $validated['verification_method'] === 'founder_email_match'
            && (! $contact->email || strcasecmp($contact->email, $startup->contact_email) !== 0)
        ) {
            return back()
                ->withErrors(['verification_method' => 'The contact email must match the incubatee record email to use this verification method.'])
                ->withInput();
        }

        ContactStartupAssignment::query()->updateOrCreate(
            [
                'contact_id' => $contact->id,
                'startup_id' => $startup->id,
            ],
            [
                'assigned_by' => $request->user()->id,
                'confirmed_by' => $request->user()->id,
                'verification_method' => $validated['verification_method'],
                'verification_notes' => $validated['verification_notes'],
                'confirmed_at' => now(),
            ]
        );

        return to_route('contacts')->with('contact_status', $contact->name.' was assigned to '.$startup->name.' and the relationship was confirmed.');
    }

    public function removeStartupAssignment(Contact $contact, Startup $startup): RedirectResponse
    {
        $contact->startupAssignments()->where('startup_id', $startup->id)->delete();

        return to_route('contacts')->with('contact_status', 'The incubatee assignment was removed.');
    }

    private function attributes(SaveContactRequest $request): array
    {
        $validated = $request->validated();

        return [
            'side' => $validated['side'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'affiliation_type' => $validated['affiliation_type'],
            'role' => $validated['role'],
            'organization' => $validated['organization'] ?? null,
            'academic_program' => $validated['academic_program'] ?? null,
            'academic_year' => $validated['academic_year'] ?? null,
            'domains' => $validated['domains'] ?? [],
            'domain_other' => $request->boolean('domains_other_selected') ? $request->input('domain_other') : null,
            'technical_skills' => $validated['technical_skills'] ?? [],
            'technical_other' => $request->boolean('technical_other_selected') ? $request->input('technical_other') : null,
            'skill_details' => $validated['skill_details'] ?? null,
            'nda_status' => $validated['nda_status'],
        ];
    }

    private function ensureSampleContacts(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        foreach (config('contacts', []) as $contact) {
            Contact::withTrashed()->firstOrCreate(
                ['id' => $contact['id']],
                $contact + [
                    'email' => null,
                    'phone' => null,
                    'organization' => null,
                    'academic_program' => null,
                    'academic_year' => null,
                    'domain_other' => null,
                    'technical_other' => null,
                ]
            );
        }
    }
}
