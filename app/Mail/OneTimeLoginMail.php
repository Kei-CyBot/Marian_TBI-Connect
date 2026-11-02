<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OneTimeLoginMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your one-time Marian TBI Connect sign-in link');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.one-time-login');
    }
}
