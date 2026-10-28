<?php

namespace App\Http\Controllers;

use App\Mail\AccountProvisionedMail;
use App\Mail\ContactRegistrationNotificationMail;
use App\Mail\ContactRegistrationReceiptMail;
use App\Models\Contact;
use App\Models\ContactRegistrationApplication;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactRegistrationController extends Controller
{
    public function create(): View
    {
        return view('public.contact-registration');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'organization' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'stream' => ['nullable', Rule::in(['internal', 'external'])],
            'affiliation_type' => ['nullable', 'string', 'max:255'],
        ]);

        $email = (string) $validated['email'];
        $isUic = Str::endsWith(Str::lower($email), '@uic.edu.ph');
        $isGmail = Str::endsWith(Str::lower($email), '@gmail.com');

        $affiliationType = $validated['affiliation_type'] ?? null;
        if (! $affiliationType) {
            if ($isUic) {
                $affiliationType = 'UIC Internal / Institutional';
            } elseif ($isGmail) {
                $affiliationType = 'External / General';
            } else {
                $affiliationType = 'External / General';
            }
        }

        $streamLabel = ($validated['stream'] ?? ($isUic ? 'internal' : 'external')) === 'internal' ? 'Internal Academic' : 'External Industry';
        $streamValue = $validated['stream'] ?? ($isUic ? 'internal' : 'external');

        $data = [
            'name' => $validated['name'],
            'email' => $email,
            'phone' => $validated['phone'] ?? '',
            'stream' => $streamLabel,
            'affiliationType' => $affiliationType,
            'affiliation_type' => $affiliationType,
            'affiliationOther' => '',
            'organization' => $validated['organization'] ?? '',
            'role' => $validated['role'] ?? '',
            'roleOther' => '',
            'academicProgram' => '',
            'academicYear' => '',
            'domains' => [],
            'domainOther' => '',
            'technicalSkills' => [],
            'technicalOther' => '',
            'skillDetails' => '',
        ];

        $application = ContactRegistrationApplication::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'stream' => $streamValue,
            'application_data' => $data,
        ]);

        Mail::to($application->email)->queue(new ContactRegistrationReceiptMail($application->name, $application->email, $isUic));
        Mail::to(config('mail.admin_address', config('mail.from.address')))->queue(new ContactRegistrationNotificationMail($application));

        return to_route('contact-registration')->with('registration_status', 'Your application was submitted for administrator review.');
    }

    public function index(): View
    {
        $applications = ContactRegistrationApplication::query()
            ->latest()
            ->get()
            ->map(fn (ContactRegistrationApplication $application): array => [
                ...$application->application_data,
                'id' => $application->id,
                'submitted' => $application->created_at->format('Y-m-d'),
                'status' => $application->status,
            ])
            ->all();

        return view('contact-intake', compact('applications'));
    }

    public function verify(Request $request, ContactRegistrationApplication $application): RedirectResponse
    {
        if ($application->status !== 'Pending') {
            return to_route('contact-intake')->with('intake_status', 'This application has already been reviewed.');
        }

        $data = $application->application_data ?? [];

        Contact::create([
            'side' => $data['stream'] ?? $application->stream,
            'name' => $application->name,
            'email' => $application->email,
            'phone' => $data['phone'] ?? null,
            'affiliation_type' => $data['affiliationType'] ?? $data['affiliation_type'] ?? 'Other',
            'role' => $data['role'] ?? 'Other',
            'organization' => $data['organization'] ?? null,
            'academic_program' => $data['academicProgram'] ?? null,
            'academic_year' => $data['academicYear'] ?? null,
            'domains' => $data['domains'] ?? [],
            'domain_other' => $data['domainOther'] ?? null,
            'technical_skills' => $data['technicalSkills'] ?? [],
            'technical_other' => $data['technicalOther'] ?? null,
            'skill_details' => $data['skillDetails'] ?? null,
            'verification_status' => 'Verified Contact',
            'incubatees' => [],
            'activity_log' => [],
        ]);

        $application->forceFill([
            'status' => 'Verified',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        return to_route('contact-intake')->with('intake_status', $application->name.' was verified. No login account has been created.');
    }

    public function approveAndProvision(Request $request, ContactRegistrationApplication $application): RedirectResponse
    {
        if ($application->status !== 'Pending') {
            return to_route('contact-intake')->with('intake_status', 'This application has already been reviewed.');
        }

        $data = $application->application_data ?? [];
        $tempPassword = (string) random_int(10000000, 99999999);

        $user = User::firstOrCreate(
            ['email' => $application->email],
            [
                'name' => $application->name,
                'password' => Hash::make($tempPassword),
                'role' => 'member_incubatee',
            ]
        );

        if (! $user->wasRecentlyCreated) {
            $user->update(['password' => Hash::make($tempPassword)]);
        }

        if (! Contact::query()->where('email', $application->email)->exists()) {
            Contact::create([
                'side' => $data['stream'] ?? $application->stream,
                'name' => $application->name,
                'email' => $application->email,
                'phone' => $data['phone'] ?? null,
                'affiliation_type' => $data['affiliationType'] ?? $data['affiliation_type'] ?? 'Other',
                'role' => $data['role'] ?? 'Other',
                'organization' => $data['organization'] ?? null,
                'academic_program' => $data['academicProgram'] ?? null,
                'academic_year' => $data['academicYear'] ?? null,
                'domains' => $data['domains'] ?? [],
                'domain_other' => $data['domainOther'] ?? null,
                'technical_skills' => $data['technicalSkills'] ?? [],
                'technical_other' => $data['technicalOther'] ?? null,
                'skill_details' => $data['skillDetails'] ?? null,
                'verification_status' => 'Verified Contact',
                'incubatees' => [],
                'activity_log' => [],
            ]);
        } else {
            Contact::query()->where('email', $application->email)->update(['verification_status' => 'Verified Contact']);
        }

        Mail::to($application->email)->queue(new AccountProvisionedMail($application->email, $tempPassword, route('login')));

        $application->forceFill([
            'status' => 'Approved & Account Provisioned',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        return to_route('contact-intake')->with('intake_status', $application->name.' approved and invited.');
    }

    public function destroy(ContactRegistrationApplication $application): RedirectResponse
    {
        $name = $application->name;
        $application->delete();

        return to_route('contact-intake')->with('intake_status', $name."'s registration application was deleted.");
    }
}
