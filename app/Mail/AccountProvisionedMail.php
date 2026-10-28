<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountProvisionedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $tempPassword,
        public string $loginUrl,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Your Marian TBI Connect account has been provisioned')
            ->view('emails.account-provisioned');
    }
}
