<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveStartupRequest;
use App\Mail\AccountProvisionedMail;
use App\Models\Contact;
use App\Models\FormSubmission;
use App\Models\IntakeSubmission;
use App\Models\Startup;
use App\Models\StartupDriveLink;
use App\Models\User;
use App\Support\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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

    public function approveIntake(Request $request, IntakeSubmission $submission, GoogleDriveService $drive): RedirectResponse
    {
        abort_unless($submission->type === 'startup', 404);

        if (strcasecmp((string) $submission->status, 'Pending') !== 0) {
            return to_route('incubatees', ['tab' => 'pending'])
                ->with('error', 'This application has already been processed.');
        }

        $data = $submission->data ?? [];
        $email = mb_strtolower(trim((string) ($data['email'] ?? $submission->email)));
        $startupName = trim((string) ($data['startup_name'] ?? $data['name'] ?? $submission->name));
        $founderName = trim((string) ($data['founder'] ?? $data['name'] ?? $submission->name));
        $phone = $data['phone'] ?? null;
        $affiliation = $data['affiliation'] ?? $data['dept'] ?? null;
        $organization = $data['organization'] ?? null;
        $description = $data['description'] ?? $data['pitch'] ?? null;
        $classification = strtolower((string) ($data['internal_external'] ?? ''));
        $track = match (true) {
            $classification === 'internal' => 'internal',
            $classification === 'external' => 'external',
            ($data['stream'] ?? null) === 'academic' => 'internal',
            default => $submission->track,
        };
        $temporaryPassword = null;
        $startup = null;

        DB::transaction(function () use ($request, $submission, $data, $email, $startupName, $founderName, $phone, $affiliation, $organization, $description, $track, &$temporaryPassword, &$startup): void {
            $temporaryPassword = sprintf('Tbi-%04d', random_int(0, 9999));

            $attributes = [
                'name' => $startupName,
                'founder' => $founderName,
                'contact_email' => $email,
                'track' => $track,
                'organization' => $organization,
                'description' => $description,
                'venture_type' => $data['venture_type'] ?? null,
                'status' => 'Incubatee',
                'cohort_id' => $data['cohort_id'] ?? 1,
                'campus_department' => $track === 'internal' ? $affiliation : null,
                'legal_entity_type' => $data['entity'] ?? null,
            ];

            $contact = $this->registerFounderContact($attributes);

            $startup = Startup::query()->create($attributes + [
                'founder_contact_id' => $contact->id,
                'folder_access_manager_id' => $request->user()->id,
            ]);

            $founder = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $founderName,
                    'phone' => $phone,
                    'affiliation' => $affiliation,
                    'password' => Hash::make($temporaryPassword),
                    'role' => User::ROLE_MEMBER_INCUBATEE,
                    'startup_id' => $startup->id,
                ]
            );

            if (! $founder->wasRecentlyCreated) {
                $temporaryPassword = null;
                $founder->forceFill([
                    'startup_id' => $founder->startup_id ?: $startup->id,
                    'phone' => $founder->phone ?: $phone,
                    'affiliation' => $founder->affiliation ?: $affiliation,
                ])->save();
            }

            $submission->update(['status' => 'approved']);
        });

        $driveUrl = $data['drive_link'] ?? $data['drive_url'] ?? $data['drive_folder_url'] ?? $data['folder_url'] ?? null;
        if (is_string($driveUrl) && ($folderId = GoogleDriveService::extractFolderId($driveUrl))) {
            $startup->forceFill([
                'google_drive_folder_id' => $folderId,
                'google_drive_folder_url' => $driveUrl,
            ])->save();
        }

        try {
            $folder = $drive->ensureStartupFolder($startup);
            if (is_array($folder)) {
                $startup->forceFill([
                    'google_drive_folder_id' => $folder['id'],
                    'google_drive_folder_url' => $folder['url'],
                ])->save();
            }
        } catch (\Throwable $exception) {
            Log::warning('Google Drive provisioning failed during startup approval.', [
                'startup_id' => $startup->id,
                'error' => $exception->getMessage(),
            ]);
        }

        if ($temporaryPassword !== null) {
            Mail::to($email)->queue(new AccountProvisionedMail($email, $temporaryPassword, route('login')));
        }

        $status = $startup->name.' was approved and onboarded.';
        if ($temporaryPassword !== null) {
            $status .= ' Temporary password: '.$temporaryPassword;
        }

        return to_route('incubatees', ['tab' => 'active'])
            ->with('startup_status', $status);
    }

    public function rejectIntake(IntakeSubmission $submission): RedirectResponse
    {
        abort_unless($submission->type === 'startup', 404);

        if (strcasecmp((string) $submission->status, 'Pending') === 0) {
            $submission->update(['status' => 'rejected']);
        }

        return to_route('incubatees', ['tab' => 'pending'])
            ->with('startup_status', $submission->name.' application rejected.');
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
