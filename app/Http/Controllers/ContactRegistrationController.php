<?php

namespace App\Http\Controllers;

use App\Mail\TeamInviteMail;
use App\Models\Contact;
use App\Models\ContactRegistrationApplication;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $stream = $request->input('stream');
        $roles = $stream === 'internal'
            ? ['University Faculty Advisor', 'Campus Researcher', 'Student Lead', 'Department Chair', 'TTO Officer', 'Other']
            : ['Industry Mentor', 'Venture Capitalist', 'Angel Investor', 'Corporate Partner', 'Government Grant Officer', 'Legal/Compliance Advisor', 'Other'];
        $affiliations = $stream === 'internal'
            ? ['University department / office', 'Research lab / center', 'Student organization / academic unit', 'Other']
            : ['Company / Corporation', 'Startup / Small business', 'Freelancer / Self-employed', 'Government agency', 'Investor / VC fund', 'Nonprofit / NGO', 'Professional firm / Consultancy', 'Other'];
        $domains = ['Health / Life Sciences', 'Information Technology', 'Agriculture / Food Systems', 'Education', 'Finance / Fintech', 'Clean Energy / Environment', 'Engineering / Manufacturing', 'Business / Entrepreneurship', 'Legal / Compliance', 'Other'];
        $technicalSkills = ['Programming / Software Development', 'Web Development', 'Mobile App Development', 'Data Analytics / Data Science', 'Artificial Intelligence / Machine Learning', 'Cybersecurity', 'Cloud / DevOps', 'UI / UX Design', 'Hardware / Electronics / IoT', 'Research Methods', 'Product / Project Management', 'Other'];

        $validator = Validator::make($request->all(), [
            'stream' => ['required', Rule::in(['internal', 'external'])],
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'affiliation_type' => ['required', Rule::in($affiliations)],
            'affiliation_other' => ['nullable', 'string', 'max:100'],
            'organization' => ['nullable', 'required_unless:affiliation_type,Freelancer / Self-employed', 'string', 'max:160'],
            'role' => ['required', Rule::in($roles)],
            'role_other' => ['nullable', 'string', 'max:120'],
            'academic_program' => ['nullable', 'required_if:role,Student Lead', 'string', 'max:120'],
            'academic_year' => ['nullable', 'required_if:role,Student Lead', Rule::in(['1st year', '2nd year', '3rd year', '4th year', '5th year or higher', 'Graduate student'])],
            'domains' => ['required', 'array', 'min:1', 'max:20'],
            'domains.*' => ['required', Rule::in($domains)],
            'domain_other' => ['nullable', 'string', 'max:120'],
            'technical_skills' => ['nullable', 'array', 'max:20'],
            'technical_skills.*' => ['required', Rule::in($technicalSkills)],
            'technical_other' => ['nullable', 'string', 'max:160'],
            'skill_details' => ['nullable', 'string', 'max:200'],
        ]);
        $validator->after(function ($validator) use ($request): void {
            if ($request->input('affiliation_type') === 'Other' && ! filled($request->input('affiliation_other'))) {
                $validator->errors()->add('affiliation_other', 'Specify your affiliation type.');
            }
            if ($request->input('role') === 'Other' && ! filled($request->input('role_other'))) {
                $validator->errors()->add('role_other', 'Specify your role.');
            }
            if (in_array('Other', (array) $request->input('domains', []), true) && ! filled($request->input('domain_other'))) {
                $validator->errors()->add('domain_other', 'Specify your other domain expertise.');
            }
            if (in_array('Other', (array) $request->input('technical_skills', []), true) && ! filled($request->input('technical_other'))) {
                $validator->errors()->add('technical_other', 'Specify your other technical skill.');
            }
        });
        $validated = $validator->validate();

        $streamLabel = $validated['stream'] === 'internal' ? 'Internal Academic' : 'External Industry';
        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? '',
            'stream' => $streamLabel,
            'affiliationType' => $validated['affiliation_type'],
            'affiliationOther' => $validated['affiliation_other'] ?? '',
            'organization' => $validated['organization'] ?? '',
            'role' => $validated['role'],
            'roleOther' => $validated['role_other'] ?? '',
            'academicProgram' => $validated['academic_program'] ?? '',
            'academicYear' => $validated['academic_year'] ?? '',
            'domains' => array_values(array_diff($validated['domains'], ['Other'])),
            'domainOther' => in_array('Other', $validated['domains'], true) ? ($validated['domain_other'] ?? '') : '',
            'technicalSkills' => array_values(array_diff($validated['technical_skills'] ?? [], ['Other'])),
            'technicalOther' => in_array('Other', $validated['technical_skills'] ?? [], true) ? ($validated['technical_other'] ?? '') : '',
            'skillDetails' => $validated['skill_details'] ?? '',
        ];

        ContactRegistrationApplication::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'stream' => $validated['stream'],
            'application_data' => $data,
        ]);

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
        }

        if (! TeamInvitation::query()->where('email', $application->email)->whereNull('accepted_at')->where('expires_at', '>', now())->exists()) {
            $invitation = TeamInvitation::create([
                'email' => $application->email,
                'role' => User::ROLE_MEMBER_INCUBATEE,
                'token' => Str::random(64),
                'expires_at' => now()->addDays(7),
                'invited_by' => $request->user()->id,
            ]);

            Mail::to($invitation->email)->queue(new TeamInviteMail($invitation));
        }

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
