<?php

namespace App\Mail;

use App\Models\ContactRegistrationApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactRegistrationNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContactRegistrationApplication $application,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('New contact registration application')
            ->view('emails.contact-registration-notification');
    }
}
