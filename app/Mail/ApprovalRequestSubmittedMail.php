<?php

namespace App\Mail;

use App\Models\ApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApprovalRequestSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ApprovalRequest $approvalRequest,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('approval.mail.reviewer-subject', [
                'title' => $this->approvalRequest->bundle->title ?? __('approval.untitled-bundle'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.approval-request-submitted',
        );
    }
}
