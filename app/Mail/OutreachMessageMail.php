<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OutreachMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $messageBody,
        public readonly string $messageType = 'email',
        public readonly array $attachmentMeta = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.outreach-message',
            with: [
                'messageBody' => $this->messageBody,
                'messageType' => $this->messageType,
            ],
        );
    }

    public function attachments(): array
    {
        return array_map(
            fn (array $attachment): Attachment => Attachment::fromStorageDisk('public', $attachment['path'])
                ->as($attachment['name']),
            $this->attachmentMeta,
        );
    }
}
