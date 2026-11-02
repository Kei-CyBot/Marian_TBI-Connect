<?php

namespace App\Mail;

use App\Models\IntakeSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IntakeSubmissionReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly IntakeSubmission $submission
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->submission->type === 'startup'
                ? 'Startup application received'
                : 'Alumni update received',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.intake-submission-receipt',
        );
    }
}
