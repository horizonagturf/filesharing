<?php

namespace Tests\Feature;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ShareMode;
use App\Mail\ApprovalRequestSubmittedMail;
use App\Mail\BundleApprovedMail;
use App\Mail\BundleDeniedMail;
use App\Mail\BundleInvitationMail;
use App\Mail\BundleOtpMail;
use App\Models\ApprovalRequest;
use App\Models\Bundle;
use App\Models\BundleRecipient;
use App\Models\File;
use App\Models\User;
use App\Services\BrandingSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Str;
use Tests\TestCase;

class MailRenderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $branding = app(BrandingSettings::class);
        $branding->set(BrandingSettings::KEY_APP_NAME, 'Branded Org');
        $branding->set(BrandingSettings::KEY_LOGO_PATH, 'branding/logo.png');
        $branding->set(BrandingSettings::KEY_PRIMARY_COLOR, '#112233');
        $branding->set(BrandingSettings::KEY_FOOTER_TEXT, 'Confidential');
        $branding->set(BrandingSettings::KEY_TOS_URL, 'https://example.com/terms');
        $branding->set(BrandingSettings::KEY_PRIVACY_URL, 'https://example.com/privacy');
    }

    private function renderMail(Mailable $mail): array
    {
        $method = new \ReflectionMethod($mail, 'renderForAssertions');
        $method->setAccessible(true);

        return $method->invoke($mail);
    }

    public function test_bundle_invitation_mail_renders_branded_html_and_text(): void
    {
        $recipient = $this->createRecipient();

        $mail = new BundleInvitationMail($recipient);
        [$html, $text] = $this->renderMail($mail);

        $this->assertStringContainsString('Branded Org', $html);
        $this->assertStringContainsString('storage/branding/logo.png', $html);
        $this->assertStringContainsString('#112233', $html);
        $this->assertStringContainsString('Confidential', $html);
        $this->assertStringContainsString('https://example.com/terms', $html);
        $this->assertStringContainsString(htmlspecialchars($mail->invitationUrl, ENT_QUOTES), $html);
        $this->assertStringContainsString(__('invitation.mail.invitation-cta'), $html);
        $this->assertStringContainsString('Q4 Reports', $html);

        $this->assertStringContainsString(__('invitation.mail.invitation-body'), $text);
        $this->assertStringContainsString($mail->invitationUrl, $text);
        $this->assertStringContainsString('Q4 Reports', $text);
    }

    public function test_bundle_otp_mail_renders_branded_html_and_text(): void
    {
        $recipient = $this->createRecipient();

        $mail = new BundleOtpMail($recipient, '123456');
        [$html, $text] = $this->renderMail($mail);

        $this->assertStringContainsString('Branded Org', $html);
        $this->assertStringContainsString('#112233', $html);
        $this->assertStringContainsString('123456', $html);
        $this->assertStringContainsString(__('invitation.mail.otp-body'), $html);

        $this->assertStringContainsString('123456', $text);
        $this->assertStringContainsString(__('invitation.mail.otp-body'), $text);
    }

    public function test_approval_request_submitted_mail_renders_branded_html_and_text(): void
    {
        $approvalRequest = $this->createApprovalRequest();
        $reviewUrl = route('approval.show', $approvalRequest);

        $mail = new ApprovalRequestSubmittedMail($approvalRequest);
        [$html, $text] = $this->renderMail($mail);

        $this->assertStringContainsString('Branded Org', $html);
        $this->assertStringContainsString('#112233', $html);
        $this->assertStringContainsString($reviewUrl, $html);
        $this->assertStringContainsString(__('approval.mail.reviewer-cta'), $html);
        $this->assertStringContainsString('Queue Uploader', $html);

        $this->assertStringContainsString(__('approval.mail.reviewer-body'), $text);
        $this->assertStringContainsString($reviewUrl, $text);
    }

    public function test_bundle_approved_mail_renders_links_for_direct_share_mode(): void
    {
        $bundle = $this->createBundle(ShareMode::StaticLink);
        $bundle->update([
            'preview_link' => 'https://example.com/preview',
            'download_link' => 'https://example.com/download',
        ]);

        $mail = new BundleApprovedMail($bundle);
        [$html, $text] = $this->renderMail($mail);

        $this->assertStringContainsString('Branded Org', $html);
        $this->assertStringContainsString('https://example.com/preview', $html);
        $this->assertStringContainsString('https://example.com/download', $html);
        $this->assertStringContainsString(__('approval.mail.approved-preview-cta'), $html);

        $this->assertStringContainsString('https://example.com/preview', $text);
        $this->assertStringContainsString('https://example.com/download', $text);
    }

    public function test_bundle_approved_mail_renders_invitation_message_for_invitation_mode(): void
    {
        $bundle = $this->createBundle(ShareMode::Invitation);
        $viewUrl = route('upload.create.show', $bundle);

        $mail = new BundleApprovedMail($bundle);
        [$html, $text] = $this->renderMail($mail);

        $this->assertStringContainsString(__('approval.mail.approved-invitations-sent'), $html);
        $this->assertStringContainsString(__('approval.mail.approved-view-bundle-cta'), $html);
        $this->assertStringContainsString($viewUrl, $html);
        $this->assertStringNotContainsString(__('approval.mail.approved-preview-cta'), $html);

        $this->assertStringContainsString(__('approval.mail.approved-invitations-sent'), $text);
        $this->assertStringContainsString($viewUrl, $text);
    }

    public function test_bundle_denied_mail_renders_branded_html_and_text(): void
    {
        $bundle = $this->createBundle(ShareMode::StaticLink);
        $editUrl = route('upload.create.show', $bundle);

        $mail = new BundleDeniedMail($bundle, 'Contains sensitive data');
        [$html, $text] = $this->renderMail($mail);

        $this->assertStringContainsString('Branded Org', $html);
        $this->assertStringContainsString('#112233', $html);
        $this->assertStringContainsString('Contains sensitive data', $html);
        $this->assertStringContainsString($editUrl, $html);
        $this->assertStringContainsString(__('approval.mail.denied-cta'), $html);

        $this->assertStringContainsString('Contains sensitive data', $text);
        $this->assertStringContainsString($editUrl, $text);
    }

    private function createBundle(ShareMode $shareMode = ShareMode::Invitation): Bundle
    {
        $user = User::factory()->create(['name' => 'Queue Uploader']);
        $slug = 'bundle-'.Str::lower(Str::random(8));

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => 'Q4 Reports',
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'share_mode' => $shareMode,
            'require_otp' => true,
            'completed' => true,
            'status' => 'approved',
            'expiry' => '86400',
            'fullsize' => 2_500_000,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'report.pdf',
            'filesize' => 2_500_000,
            'fullpath' => "{$slug}/report.pdf",
            'filename' => 'report.pdf',
            'status' => true,
        ]);

        return $bundle->fresh(['files', 'user']);
    }

    private function createRecipient(): BundleRecipient
    {
        $bundle = $this->createBundle();

        return BundleRecipient::create([
            'bundle_id' => $bundle->id,
            'email' => 'recipient@example.com',
        ])->load('bundle');
    }

    private function createApprovalRequest(): ApprovalRequest
    {
        $bundle = $this->createBundle();

        return ApprovalRequest::create([
            'bundle_id' => $bundle->id,
            'requested_by' => $bundle->user_id,
            'status' => ApprovalRequestStatus::Pending,
        ])->load(['bundle.files', 'requester']);
    }
}
