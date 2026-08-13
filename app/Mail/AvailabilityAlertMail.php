<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AvailabilityAlertMail extends Mailable
{
    public function __construct(
        public string $url,
        public bool $recovered,
        public string $checkedAt,
        public ?string $failureReason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->recovered
                ? 'Vestapp vuelve a estar disponible'
                : 'Alerta: Vestapp no está disponible',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.availability-alert');
    }
}
