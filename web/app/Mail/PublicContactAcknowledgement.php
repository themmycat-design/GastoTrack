<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PublicContactAcknowledgement extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $senderName,
        public string $teamEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thank you for contacting GastoTrack',
            replyTo: [new Address($this->teamEmail, 'GastoTrack Team')],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.public-contact-acknowledgement');
    }
}
