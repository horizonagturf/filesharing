<?php

namespace App\Mail;

use App\Models\BundleRecipient;
use App\Services\BrandingSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BundleOtpMail extends Mailable implements ShouldQueue
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
                'app' => app(BrandingSettings::class)->appName(),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.bundle-otp-html',
            text: 'mail.bundle-otp',
        );
    }
}
