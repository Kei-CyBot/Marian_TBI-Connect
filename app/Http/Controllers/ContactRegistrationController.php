<?php

namespace App\Http\Controllers;

use App\Mail\ContactRegistrationNotificationMail;
use App\Mail\ContactRegistrationReceiptMail;
use App\Mail\UserCredentialInviteMail;
use App\Models\Contact;
use App\Models\ContactRegistrationApplication;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'department' => ['nullable', 'string', 'max:255'],
            'track_stream' => ['nullable', 'string', 'max:255'],
            'domain_expertise' => ['nullable'],
            'domains' => ['nullable'],
            'technical_skills' => ['nullable'],
            'tools_methods' => ['nullable', 'array'],
            'tools_methods.*' => ['nullable', 'string', 'max:100'],
            'tools_methods_other' => ['nullable', 'string', 'max:255'],
            'skill_details' => ['nullable'],
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
            'phone' => !empty($validated['phone']) ? $validated['phone'] : null,
            'stream' => $streamLabel,
            'affiliationType' => $affiliationType,
            'affiliation_type' => $affiliationType,
            'affiliationOther' => null,
            'organization' => !empty($validated['organization']) ? $validated['organization'] : null,
            'role' => !empty($validated['role']) ? $validated['role'] : null,
            'roleOther' => null,
            'academicProgram' => null,
            'academicYear' => null,
            'domains' => [],
            'domain_expertise' => [],
            'domainOther' => null,
            'technicalSkills' => [],
            'technical_skills' => [],
            'technicalOther' => null,
            'skillDetails' => null,
            'tools_methods' => [],
        ];

        $domainExpertise = $request->input('domain_expertise', $request->input('domains', $data['domains'] ?? []));
        if (is_string($domainExpertise)) {
            $domainExpertise = array_filter(array_map('trim', explode(',', $domainExpertise)));
        } elseif (is_array($domainExpertise)) {
            $domainExpertise = array_values(array_filter($domainExpertise));
        } else {
            $domainExpertise = [];
        }

        $technicalSkills = $request->input('technical_skills', $data['technicalSkills'] ?? []);
        if (is_string($technicalSkills)) {
            $technicalSkills = array_filter(array_map('trim', explode(',', $technicalSkills)));
        } elseif (is_array($technicalSkills)) {
            $technicalSkills = array_values(array_filter($technicalSkills));
        } else {
            $technicalSkills = [];
        }

        $toolsMethods = $request->input('tools_methods', $request->input('skill_details', []));
        if (is_string($toolsMethods)) {
            $toolsMethods = array_filter(array_map('trim', explode(',', $toolsMethods)));
        } elseif (is_array($toolsMethods)) {
            $toolsMethods = array_values(array_filter(array_map(function ($v) {
                return is_string($v) ? trim($v) : $v;
            }, $toolsMethods)));
        } else {
            $toolsMethods = [];
        }

        if ($request->filled('tools_methods_other')) {
            $toolsMethods[] = trim((string) $request->input('tools_methods_other'));
        }
        $toolsMethods = array_values(array_filter($toolsMethods, function ($v) {
            return is_string($v) ? trim($v) !== '' : !empty($v);
        }));

        $application = ContactRegistrationApplication::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => !empty($data['phone']) ? $data['phone'] : null,
            'organization' => !empty($validated['organization']) ? $validated['organization'] : (!empty($data['organization']) ? $data['organization'] : null),
            'role' => !empty($data['role']) ? $data['role'] : null,
            'department' => $request->filled('department') ? $request->input('department') : null,
            'affiliation_type' => $affiliationType,
            'stream' => $streamValue,
            'track_stream' => $request->input('track_stream', $streamValue),
            'domain_expertise' => array_values(array_filter((array) $domainExpertise)),
            'technical_skills' => array_values(array_filter((array) $technicalSkills)),
            'tools_methods' => array_values(array_filter((array) $toolsMethods)),
            'application_data' => array_merge($data, [
                'organization' => !empty($validated['organization']) ? $validated['organization'] : (!empty($data['organization']) ? $data['organization'] : null),
                'role' => !empty($validated['role']) ? $validated['role'] : (!empty($data['role']) ? $data['role'] : null),
                'phone' => !empty($validated['phone']) ? $validated['phone'] : ($data['phone'] ?? null),
                'department' => $request->filled('department') ? $request->input('department') : null,
                'domains' => array_values(array_filter((array) $domainExpertise)),
                'domain_expertise' => array_values(array_filter((array) $domainExpertise)),
                'domain_other' => $request->input('domain_other') ?: ($data['domainOther'] ?? null),
                'technicalSkills' => array_values(array_filter((array) $technicalSkills)),
                'technical_skills' => array_values(array_filter((array) $technicalSkills)),
                'technical_other' => $request->input('technical_other') ?: ($data['technicalOther'] ?? null),
                'skillDetails' => is_array($toolsMethods) && !empty(array_filter($toolsMethods)) ? implode(', ', array_filter($toolsMethods)) : ($request->input('skill_details') ?: ($data['skillDetails'] ?? null)),
                'tools_methods' => array_values(array_filter((array) $toolsMethods)),
                'academicProgram' => $request->input('academic_program') ?: null,
                'academicYear' => $request->input('academic_year') ?: null,
            ]),
        ]);

        Mail::to($application->email)->queue(new ContactRegistrationReceiptMail($application->name, $application->email, $isUic));
        Mail::to(config('mail.admin_address', config('mail.from.address')))->queue(new ContactRegistrationNotificationMail($application));

        return redirect()->route('contact-registration')->with('submitted', true)->with('success', 'Thank you! Your registration has been submitted successfully.')->with('registration_status', 'Your application was submitted for administrator review.');
    }

    public function index(): View
    {
        $applications = ContactRegistrationApplication::query()
            ->where('status', 'Pending')
            ->latest()
            ->get()
            ->map(function (ContactRegistrationApplication $application): array {
                $data = $application->application_data ?? [];

                $domains = array_values(array_filter((array) ($data['domains'] ?? $data['domain_expertise'] ?? $application->domain_expertise ?? [])));
                $technicalSkills = array_values(array_filter((array) ($data['technicalSkills'] ?? $data['technical_skills'] ?? $application->technical_skills ?? [])));
                $toolsMethods = array_values(array_filter((array) ($data['tools_methods'] ?? [])));

                return array_merge($data, [
                    'id' => $application->id,
                    'submitted' => $application->created_at->format('Y-m-d'),
                    'status' => $application->status,
                    'domains' => $domains,
                    'domain_expertise' => $domains,
                    'technicalSkills' => $technicalSkills,
                    'technical_skills' => $technicalSkills,
                    'tools_methods' => $toolsMethods,
                    'skillDetails' => $data['skillDetails'] ?? (is_array($toolsMethods) && !empty($toolsMethods) ? implode(', ', $toolsMethods) : ($application->tools_methods && is_array($application->tools_methods) && !empty(array_filter($application->tools_methods)) ? implode(', ', array_filter($application->tools_methods)) : null)),
                    'organization' => !empty($data['organization']) ? $data['organization'] : ($application->organization ?? null),
                    'role' => !empty($data['role']) ? $data['role'] : ($application->role ?? null),
                    'phone' => !empty($data['phone']) ? $data['phone'] : ($application->phone ?? null),
                    'affiliationType' => $data['affiliationType'] ?? $data['affiliation_type'] ?? $application->affiliation_type ?? null,
                    'affiliation_type' => $data['affiliation_type'] ?? $data['affiliationType'] ?? $application->affiliation_type ?? null,
                    'stream' => $data['stream'] ?? $application->stream ?? null,
                ]);
            })
            ->all();

        return view('contact-intake', compact('applications'));
    }

    public function verify(Request $request, ContactRegistrationApplication $application): RedirectResponse
    {
        return $this->approveAndProvision($request, $application);
    }

    public function approveAndProvision(Request $request, ContactRegistrationApplication $application): RedirectResponse
    {
        try {
            if (strtolower((string) $application->status) !== 'pending') {
                return redirect()->back()->with('error', 'This application has already been processed.');
            }

            DB::transaction(function () use ($application) {
                $data = $application->application_data ?? [];
                $email = strtolower(trim((string) $application->email));

                $domains = array_values(array_filter((array) ($data['domains'] ?? $data['domain_expertise'] ?? $application->domain_expertise ?? [])));
                $technicalSkills = array_values(array_filter((array) ($data['technicalSkills'] ?? $data['technical_skills'] ?? $application->technical_skills ?? [])));

                $role = !empty($data['role']) ? $data['role'] : ($application->role ?? 'Applicant');
                $affiliation = $data['affiliationType'] ?? $data['affiliation_type'] ?? $application->affiliation_type ?? null;
                $affiliationType = !empty($affiliation) ? $affiliation : 'External';

                $side = strtolower((string) ($application->stream ?? $data['stream'] ?? 'external'));
                if (str_contains($side, 'internal')) {
                    $side = 'internal';
                } elseif (str_contains($side, 'external')) {
                    $side = 'external';
                }
                $side = substr($side, 0, 16);

                Contact::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $application->name,
                        'phone' => $data['phone'] ?? $application->phone ?? null,
                        'organization' => !empty($data['organization']) ? $data['organization'] : ($application->organization ?? null),
                        'role' => $role,
                        'affiliation_type' => $affiliationType,
                        'verification_status' => 'Verified Contact',
                        'side' => $side,
                        'domains' => $domains,
                        'domain_other' => $data['domainOther'] ?? $data['domain_other'] ?? null,
                        'technical_skills' => $technicalSkills,
                        'technical_other' => $data['technicalOther'] ?? $data['technical_other'] ?? null,
                        'academic_program' => $data['academicProgram'] ?? $data['academic_program'] ?? $application->department ?? null,
                        'academic_year' => $data['academicYear'] ?? $data['academic_year'] ?? null,
                        'skill_details' => $data['skillDetails'] ?? (is_array($application->tools_methods) && !empty(array_filter($application->tools_methods)) ? implode(', ', array_filter($application->tools_methods)) : ($application->tools_methods ?? null)),
                        'nda_status' => 'Pending',
                        'incubatees' => [],
                        'activity_log' => [],
                    ]
                );

                $application->update([
                    'status' => 'Approved',
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ]);
            });

            return redirect()->route('contact-intake')->with('success', 'Application approved and contact successfully moved to Contact & Networks.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Contact Approval Failed: ' . $e->getMessage(), [
                'application_id' => $application->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('contact-intake')->with('error', 'Approval failed: ' . $e->getMessage());
        }
    }

    public function destroy(ContactRegistrationApplication $application): RedirectResponse
    {
        $name = $application->name;
        $application->delete();

        return to_route('contact-intake')->with('intake_status', $name."'s registration application was deleted.");
    }
}
