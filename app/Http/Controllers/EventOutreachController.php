<?php

namespace App\Http\Controllers;

use App\Mail\OutreachMessageMail;
use App\Models\Contact;
use App\Models\OutreachMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class EventOutreachController extends Controller
{
    public function sendCommunication(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_id' => ['nullable', 'integer', 'min:1'],
            'message_type' => ['required', Rule::in(['email', 'announcement', 'reminder', 'update'])],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'recipients' => ['required', 'array', 'min:1', 'max:100'],
            'recipients.*' => ['required', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,png,jpg,zip'],
        ]);

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->with('email_error', 'Outgoing email is not configured. Set a delivery mailer in the server environment before sending.');
        }

        $recipients = Contact::query()
            ->whereIn('id', array_unique($validated['recipients']))
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->orderBy('id')
            ->get();

        if ($recipients->count() !== count(array_unique($validated['recipients']))) {
            return back()->with('email_error', 'One or more selected recipients no longer have an email address. Review the recipient list and try again.');
        }

        $attachments = collect($request->file('attachments', []))
            ->map(fn ($file): array => [
                'path' => $file->store('outreach_attachments', 'public'),
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ])
            ->all();

        $sentCount = 0;
        $failedCount = 0;
        $lastError = null;

        foreach ($recipients as $contact) {
            try {
                Mail::to($contact->email)->send(new OutreachMessageMail(
                    subjectLine: $validated['subject'],
                    messageBody: $validated['body'],
                    messageType: $validated['message_type'],
                    attachmentMeta: $attachments,
                ));
                $sentCount++;
            } catch (\Throwable $exception) {
                Log::error("Failed to send outreach message to {$contact->email}: ".$exception->getMessage());
                $lastError = $exception->getMessage();
                $failedCount++;
            }
        }

        if ($sentCount > 0) {
            OutreachMessage::query()->create([
                'event_id' => $validated['event_id'] ?? null,
                'sender_id' => $request->user()->id,
                'message_type' => $validated['message_type'],
                'subject' => $validated['subject'],
                'body' => $validated['body'],
                'attachments' => $attachments,
                'sent_at' => now(),
            ]);
        }

        $debugSuffix = config('app.debug') && $lastError ? ' '.$lastError : '';

        if ($sentCount === 0) {
            return back()->with('email_error', 'Email delivery failed. Please check your SMTP settings.'.$debugSuffix);
        }

        if ($failedCount > 0) {
            return back()->with('email_error', "Sent {$sentCount} invitation(s), but {$failedCount} recipient(s) failed.".$debugSuffix);
        }

        return back()->with('email_status', "Communication sent to {$sentCount} recipient(s).");
    }
}
