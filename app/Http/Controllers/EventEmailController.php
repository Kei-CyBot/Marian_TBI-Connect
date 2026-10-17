<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EventEmailController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contact_ids' => ['required', 'array', 'min:1', 'max:100'],
            'contact_ids.*' => ['required', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        if (! in_array(config('mail.default'), ['log', 'array'], true)) {
            $contacts = Contact::query()
                ->whereIn('id', array_unique($validated['contact_ids']))
                ->whereNotNull('email')
                ->where('email', '<>', '')
                ->orderBy('id')
                ->get();
            if ($contacts->count() !== count(array_unique($validated['contact_ids']))) {
                return back()->with('email_error', 'One or more selected contacts no longer have an email address. Review the recipient list and try again.');
            }

            $sentCount = 0;
            try {
                foreach ($contacts as $contact) {
                    Mail::to($contact->email)->send(new ContactMessage($validated['subject'], $validated['body']));
                    $sentCount++;
                }
            } catch (TransportExceptionInterface $exception) {
                Log::error('Contact email delivery failed.', [
                    'sent_count' => $sentCount,
                    'contact_ids' => $contacts->pluck('id')->all(),
                    'exception' => $exception,
                ]);

                return back()->with(
                    'email_error',
                    $sentCount
                        ? "Delivery stopped after {$sentCount} message(s). Check outgoing mail settings and retry for the remaining contacts."
                        : 'Email delivery failed. Check the outgoing mail settings and try again.'
                );
            }

            return back()->with('email_status', "Email sent individually to {$sentCount} contact(s).");
        }

        return back()->with('email_error', 'Outgoing email is not configured. Set a delivery mailer in the server environment before sending.');
    }
}
