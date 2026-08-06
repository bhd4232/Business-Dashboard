<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StorefrontLoginOtp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $companyName,
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->companyName} login verification code");
    }

    public function content(): Content
    {
        return new Content(text: 'emails.storefront-login-otp');
    }
}
