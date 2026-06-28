<?php

namespace App\Mail;

use App\Models\BundleRecipient;
use App\Services\BundleInvitationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BundleInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public readonly string $invitationUrl;

    public function __construct(
        public readonly BundleRecipient $recipient,
    ) {
        $this->invitationUrl = app(BundleInvitationService::class)->invitationUrl($recipient);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('invitation.mail.invitation-subject', [
                'title' => $this->recipient->bundle->title ?? __('invitation.untitled-bundle'),
                'app' => app(\App\Services\BrandingSettings::class)->appName(),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.bundle-invitation',
        );
    }
}
