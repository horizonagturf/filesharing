<?php

namespace App\Mail;

use App\Models\BundleRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BundleOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly BundleRecipient $recipient,
        public readonly string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('invitation.mail.otp-subject', [
                'app' => app(\App\Services\BrandingSettings::class)->appName(),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.bundle-otp',
        );
    }
}
