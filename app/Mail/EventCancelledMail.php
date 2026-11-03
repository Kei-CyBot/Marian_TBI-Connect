<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\EventInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Event $event,
        public readonly EventInvitation $invitation,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Event cancelled: '.$this->event->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.event-cancelled');
    }
}
