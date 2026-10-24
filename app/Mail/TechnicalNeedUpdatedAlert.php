<?php

namespace App\Mail;

use App\Models\TechnicalNeed;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TechnicalNeedUpdatedAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly TechnicalNeed $need
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Technical need updated - '.$this->need->requirement,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.technical-need-updated-alert',
        );
    }
}
