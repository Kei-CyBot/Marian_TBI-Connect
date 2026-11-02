<?php

namespace App\Http\Controllers;

use App\Mail\EventInvitationMail;
use App\Models\Contact;
use App\Models\Event;
use App\Models\EventInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class EventInvitationController extends Controller
{
    public function respond(string $token, string $status): View
    {
        abort_unless(in_array($status, ['attending', 'declined'], true), 404);

        $invitation = EventInvitation::query()
            ->with('event')
            ->where('token', $token)
            ->firstOrFail();

        $invitation->update([
            'status' => $status,
            'responded_at' => now(),
        ]);

        return view('events.invitation-response', [
            'invitation' => $invitation,
            'event' => $invitation->event,
        ]);
    }

    public function sendInvites(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'contact_ids' => ['nullable', 'array'],
            'contact_ids.*' => ['integer', 'exists:contacts,id'],
            'emails' => ['nullable', 'array'],
            'emails.*' => ['email', 'max:255'],
        ]);

        $contacts = Contact::query()
            ->whereIn('id', $validated['contact_ids'] ?? [])
            ->get();

        $recipients = [];
        foreach ($contacts as $contact) {
            if ($contact->email) {
                $recipients[$contact->email] = $contact;
            }
        }
        foreach ($validated['emails'] ?? [] as $email) {
            $recipients[$email] ??= null;
        }

        abort_if($recipients === [], 422, 'Select at least one recipient.');

        $sentCount = 0;
        $failedCount = 0;
        $lastError = null;
        foreach ($recipients as $email => $contact) {
            $invitation = $event->invitations()->create([
                'contact_id' => $contact?->id,
                'email' => $email,
                'name' => $contact?->name,
                'token' => Str::random(40),
                'status' => 'pending',
            ]);

            try {
                Mail::to($email)->send(new EventInvitationMail($event, $invitation));
                $sentCount++;
            } catch (Throwable $exception) {
                Log::error("Failed to send event invite to {$email}: ".$exception->getMessage());
                $lastError = $exception->getMessage();
                $failedCount++;
            }
        }

        $debugSuffix = config('app.debug') && $lastError ? ' '.$lastError : '';

        if ($sentCount === 0) {
            return back()->with('email_error', 'Email delivery failed. Please check your SMTP settings.'.$debugSuffix);
        }

        if ($failedCount > 0) {
            return back()->with('email_error', "Sent {$sentCount} invitation(s), but {$failedCount} recipient(s) failed.".$debugSuffix);
        }

        return back()->with('event_status', "Invitations sent successfully to {$sentCount} recipient(s).");
    }
}
