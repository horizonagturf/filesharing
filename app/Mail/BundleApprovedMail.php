<?php

namespace App\Mail;

use App\Models\Bundle;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BundleApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Bundle $bundle,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('approval.mail.approved-subject', [
                'title' => $this->bundle->title ?? __('approval.untitled-bundle'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.bundle-approved',
        );
    }
}
