<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\EventInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Event $event,
        public readonly EventInvitation $invitation,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You are invited: '.$this->event->title);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.event-invitation',
            with: [
                'event' => $this->event,
                'invitation' => $this->invitation,
            ],
        );
    }
}
