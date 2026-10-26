<?php

namespace App\Http\Controllers;

use App\Mail\IntakeSubmissionReceived;
use App\Models\IntakeSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class PublicIntakeController extends Controller
{
    public function storeStartup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'stream' => ['required', Rule::in(['academic', 'industry'])],
            'name' => ['required', 'string', 'max:160'],
            'founder' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'sector' => ['required', 'string', 'max:100'],
            'pitch' => ['nullable', 'string', 'max:5000'],
            'dept' => ['nullable', 'string', 'max:160'],
            'ip' => ['nullable', 'string', 'max:160'],
            'advisor' => ['nullable', 'string', 'max:160'],
            'entity' => ['nullable', 'string', 'max:80'],
            'stage' => ['nullable', 'string', 'max:80'],
            'website' => ['nullable', 'string', 'max:255'],
            'employees' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $submission = IntakeSubmission::query()->create([
            'type' => 'startup',
            'name' => $validated['name'],
            'email' => $validated['email'],
            'track' => $validated['stream'] === 'academic' ? 'internal' : 'external',
            'data' => $validated,
        ]);

        Mail::to($submission->email)->queue(new IntakeSubmissionReceived($submission));

        return to_route('startup-form')->with('intake_status', 'Your startup application was submitted for review.');
    }

    public function storeAlumni(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'stream' => ['required', Rule::in(['academic', 'industry'])],
            'name' => ['required', 'string', 'max:160'],
            'startup' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'gradYear' => ['nullable', 'integer', 'between:1900,'.((int) date('Y') + 1)],
            'status' => ['required', Rule::in(['Operating', 'Acquired', 'Pivoted', 'Paused', 'Closed'])],
            'employees' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'funding' => ['nullable', 'string', 'max:255'],
            'revenue' => ['nullable', 'string', 'max:80'],
            'licensed' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'mentor' => ['nullable', 'boolean'],
        ]);

        $submission = IntakeSubmission::query()->create([
            'type' => 'alumni',
            'name' => $validated['name'],
            'email' => $validated['email'],
            'track' => $validated['stream'] === 'academic' ? 'internal' : 'external',
            'data' => [...$validated, 'mentor' => $request->boolean('mentor')],
        ]);

        Mail::to($submission->email)->queue(new IntakeSubmissionReceived($submission));

        return to_route('alumni-form')->with('intake_status', 'Your alumni update was submitted. Thank you!');
    }
}
