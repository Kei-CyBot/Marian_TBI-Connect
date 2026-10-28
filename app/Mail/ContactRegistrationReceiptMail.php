<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactRegistrationReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public bool $isInternal,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Your Marian TBI Connect registration was received')
            ->view('emails.contact-registration-receipt');
    }
}
