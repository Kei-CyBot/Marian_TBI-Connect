<?php

namespace App\Mail;

use App\Models\FormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FormSubmittedReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly FormSubmission $submission
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Form submission receipt - '.$this->submission->form_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.form-submitted-receipt',
        );
    }
}
