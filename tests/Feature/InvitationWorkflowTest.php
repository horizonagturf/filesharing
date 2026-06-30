<?php

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Enums\BundleStatus;
use App\Enums\ShareMode;
use App\Mail\BundleInvitationMail;
use App\Mail\BundleOtpMail;
use App\Models\Bundle;
use App\Models\BundleRecipient;
use App\Models\File;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvitationWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['sharing.default_share_mode' => 'invitation']);
    }

    public function test_complete_sends_invitations_and_sets_sent_status(): void
    {
        Mail::fake();

        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user);
        $this->addRecipient($bundle, 'external@example.com');

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk()
            ->assertJsonPath('status', BundleStatus::Sent->value)
            ->assertJsonPath('preview_link', null)
            ->assertJsonCount(1, 'recipients');

        Mail::assertQueued(BundleInvitationMail::class, fn ($mail) => $mail->hasTo('external@example.com'));

        $this->assertDatabaseHas('bundle_recipients', [
            'bundle_id' => $bundle->id,
            'email' => 'external@example.com',
        ]);
    }

    public function test_invitation_email_uses_signed_url_not_preview_token(): void
    {
        Mail::fake();

        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user);
        $recipient = $this->addRecipient($bundle, 'partner@example.org');

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk();

        Mail::assertQueued(BundleInvitationMail::class, function (BundleInvitationMail $mail) use ($bundle, $recipient) {
            $this->assertStringNotContainsString($bundle->preview_token, $mail->invitationUrl);
            $this->assertStringContainsString('signature=', $mail->invitationUrl);

            return $mail->hasTo($recipient->email);
        });
    }

    public function test_complete_requires_recipients_in_invitation_mode(): void
    {
        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertStatus(422)
            ->assertJsonPath('message', __('invitation.recipients-required'));
    }

    public function test_complete_without_recipients_generates_links_when_otp_disabled(): void
    {
        Mail::fake();

        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user);
        $bundle->update(['require_otp' => false]);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk()
            ->assertJsonPath('status', BundleStatus::Approved->value)
            ->assertJsonPath('preview_link', fn ($link) => str_contains($link, 'auth='.$bundle->preview_token))
            ->assertJsonCount(0, 'recipients');

        Mail::assertNothingSent();

        $bundle->refresh();

        $this->assertSame(BundleStatus::Approved, $bundle->status);
        $this->assertTrue($bundle->completed);
        $this->assertNotNull($bundle->preview_link);
        $this->assertNotNull($bundle->download_link);

        $this->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertOk()
            ->assertSee('Test bundle');
    }

    public function test_reviewer_approval_sends_invitations_in_invitation_mode(): void
    {
        Mail::fake();
        config(['approval.required_default' => true]);

        $uploader = User::factory()->create(['requires_approval' => true]);
        $reviewer = User::factory()->reviewer()->create();
        $bundle = $this->createBundle($uploader);
        $this->addRecipient($bundle, 'recipient@example.com');

        $this->actingAsUser($uploader)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk()
            ->assertJsonPath('status', BundleStatus::PendingApproval->value);

        Mail::assertNotSent(BundleInvitationMail::class);

        $approvalRequest = $bundle->fresh()->pendingApprovalRequest;
        $this->assertNotNull($approvalRequest);

        $this->actingAsUser($reviewer)
            ->postJson(route('approval.approve', $approvalRequest), [], [
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk();

        $bundle->refresh();
        $this->assertSame(BundleStatus::Sent, $bundle->status);
        Mail::assertQueued(BundleInvitationMail::class, fn ($mail) => $mail->hasTo('recipient@example.com'));
    }

    public function test_unverified_recipient_cannot_preview_bundle(): void
    {
        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user, BundleStatus::Sent, completed: true);
        $this->addRecipient($bundle, 'guest@example.com');

        $this->get("/bundle/{$bundle->slug}/preview")
            ->assertForbidden();
    }

    public function test_recipient_completes_otp_flow_and_accesses_bundle(): void
    {
        Mail::fake();

        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user, BundleStatus::Sent, completed: true);
        $recipient = $this->addRecipient($bundle, 'guest@example.com', invited: true);

        $signedShow = URL::temporarySignedRoute('invitation.show', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        $showResponse = $this->get($signedShow)->assertOk()->assertSee('guest@example.com');

        preg_match('/<form method="POST" action="([^"]+\/otp\?[^"]+)"/', $showResponse->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        $this->post(html_entity_decode($matches[1], ENT_QUOTES))->assertRedirect();

        Mail::assertQueued(BundleOtpMail::class, fn ($mail) => $mail->hasTo('guest@example.com'));

        $recipient->refresh();
        $code = $this->extractOtpFromMail();

        $signedVerify = URL::temporarySignedRoute('invitation.otp.verify', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        $this->post($signedVerify, ['code' => $code])
            ->assertRedirect(route('bundle.preview', ['bundle' => $bundle]));

        $this->get("/bundle/{$bundle->slug}/preview")
            ->assertOk()
            ->assertSee('Test bundle');
    }

    public function test_internal_and_external_recipients_use_same_flow(): void
    {
        Mail::fake();

        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user);
        $internal = $this->addRecipient($bundle, 'colleague@company.com');
        $external = $this->addRecipient($bundle, 'partner@external.net');

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk();

        Mail::assertQueued(BundleInvitationMail::class, fn ($mail) => $mail->hasTo($internal->email));
        Mail::assertQueued(BundleInvitationMail::class, fn ($mail) => $mail->hasTo($external->email));
    }

    public function test_owner_can_resend_invitation_without_duplicate_rows(): void
    {
        Mail::fake();

        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user, BundleStatus::Sent, completed: true);
        $recipient = $this->addRecipient($bundle, 'guest@example.com', invited: true);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/recipients/{$recipient->id}/resend", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk();

        $this->assertSame(1, BundleRecipient::query()->where('bundle_id', $bundle->id)->count());
        Mail::assertQueued(BundleInvitationMail::class, 1);
    }

    public function test_owner_can_revoke_invitation(): void
    {
        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user, BundleStatus::Sent, completed: true);
        $recipient = $this->addRecipient($bundle, 'guest@example.com', invited: true);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/recipients/{$recipient->id}/revoke", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk()
            ->assertJsonPath('message', __('invitation.invitation-revoked'));

        $recipient->refresh();
        $this->assertNotNull($recipient->revoked_at);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEvent::InvitationRevoked->value,
            'recipient_email' => 'guest@example.com',
        ]);
    }

    public function test_revoked_recipient_cannot_use_signed_invitation_url(): void
    {
        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user, BundleStatus::Sent, completed: true);
        $recipient = $this->addRecipient($bundle, 'guest@example.com', invited: true);
        $recipient->update(['revoked_at' => now()]);

        $signedShow = URL::temporarySignedRoute('invitation.show', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        $this->get($signedShow)->assertNotFound();
    }

    public function test_revoked_verified_recipient_cannot_preview_bundle(): void
    {
        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user, BundleStatus::Sent, completed: true);
        $recipient = $this->addRecipient($bundle, 'guest@example.com', invited: true);
        $recipient->update(['verified_at' => now()]);

        $this->withSession(['recipient_access.'.$bundle->id => 'guest@example.com'])
            ->get("/bundle/{$bundle->slug}/preview")
            ->assertOk();

        $recipient->update(['revoked_at' => now()]);

        $this->withSession(['recipient_access.'.$bundle->id => 'guest@example.com'])
            ->get("/bundle/{$bundle->slug}/preview")
            ->assertForbidden();
    }

    public function test_resend_on_revoked_recipient_returns_422(): void
    {
        Mail::fake();

        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user, BundleStatus::Sent, completed: true);
        $recipient = $this->addRecipient($bundle, 'guest@example.com', invited: true);
        $recipient->update(['revoked_at' => now()]);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/recipients/{$recipient->id}/resend", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertStatus(422)
            ->assertJsonPath('message', __('invitation.invitation-already-revoked'));

        Mail::assertNothingSent();
    }

    public function test_non_owner_cannot_revoke_invitation(): void
    {
        $owner = User::factory()->create(['requires_approval' => false]);
        $other = User::factory()->create();
        $bundle = $this->createBundle($owner, BundleStatus::Sent, completed: true);
        $recipient = $this->addRecipient($bundle, 'guest@example.com', invited: true);

        $this->actingAsUser($other)
            ->postJson("/upload/{$bundle->slug}/recipients/{$recipient->id}/revoke", [
                'auth' => $bundle->owner_token,
            ], [
                'X-Upload-Auth' => 'wrong-token',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertForbidden();

        $this->assertNull($recipient->fresh()->revoked_at);
    }

    public function test_store_bundle_syncs_recipient_emails(): void
    {
        $user = User::factory()->create();
        $bundle = $this->createBundle($user);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}", [
                'title' => 'Updated title',
                'expiry' => '86400',
                'max_downloads' => 0,
                'recipients' => ['one@example.com', 'two@example.com'],
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk()
            ->assertJsonCount(2, 'recipients');

        $this->assertDatabaseHas('bundle_recipients', ['email' => 'one@example.com']);
        $this->assertDatabaseHas('bundle_recipients', ['email' => 'two@example.com']);
    }

    public function test_invitation_show_does_not_consume_otp_route_rate_limit(): void
    {
        Mail::fake();
        config(['security.otp_route_rate_limit_per_hour' => 1]);

        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user, BundleStatus::Sent, completed: true);
        $recipient = $this->addRecipient($bundle, 'guest@example.com', invited: true);

        $signedShow = URL::temporarySignedRoute('invitation.show', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        $this->get($signedShow)->assertOk();
        $this->get($signedShow)->assertOk();

        $signedOtp = URL::temporarySignedRoute('invitation.otp.request', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        $this->post($signedOtp)->assertRedirect();
        Mail::assertQueued(BundleOtpMail::class);
    }

    public function test_otp_verify_rejects_invalid_code(): void
    {
        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user, BundleStatus::Sent, completed: true);
        $recipient = $this->addRecipient($bundle, 'guest@example.com', invited: true);

        $recipient->update([
            'otp_hash' => Hash::make('123456'),
            'otp_expires_at' => now()->addMinutes(15),
            'otp_attempts' => 0,
        ]);

        $signedVerify = URL::temporarySignedRoute('invitation.otp.verify', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        $this->from($signedVerify)
            ->post($signedVerify, ['code' => '000000'])
            ->assertRedirect()
            ->assertSessionHasErrors('code');

        $this->assertNull($recipient->fresh()->verified_at);
    }

    public function test_signed_invitation_auto_grants_access_when_otp_not_required(): void
    {
        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user, BundleStatus::Sent, completed: true);
        $bundle->update(['require_otp' => false]);
        $recipient = $this->addRecipient($bundle, 'guest@example.com', invited: true);

        $signedShow = URL::temporarySignedRoute('invitation.show', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        $this->get($signedShow)
            ->assertRedirect(route('bundle.preview', ['bundle' => $bundle]));

        $this->assertNotNull($recipient->fresh()->verified_at);

        $this->get("/bundle/{$bundle->slug}/preview")
            ->assertOk()
            ->assertSee('Test bundle');
    }

    public function test_otp_routes_return_not_found_when_otp_not_required(): void
    {
        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user, BundleStatus::Sent, completed: true);
        $bundle->update(['require_otp' => false]);
        $recipient = $this->addRecipient($bundle, 'guest@example.com', invited: true);

        $signedOtp = URL::temporarySignedRoute('invitation.otp.request', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        $this->post($signedOtp)->assertNotFound();
    }

    private function createBundle(
        User $user,
        BundleStatus $status = BundleStatus::Draft,
        bool $completed = false,
    ): Bundle {
        $slug = 'bundle-'.Str::lower(Str::random(8));

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => 'Test bundle',
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'share_mode' => ShareMode::Invitation,
            'require_otp' => true,
            'completed' => $completed,
            'status' => $status,
            'expiry' => '86400',
            'fullsize' => 100,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'test.txt',
            'filesize' => 100,
            'fullpath' => "{$slug}/test.txt",
            'filename' => 'test.txt',
            'status' => true,
        ]);

        return $bundle;
    }

    private function addRecipient(Bundle $bundle, string $email, bool $invited = false): BundleRecipient
    {
        return BundleRecipient::create([
            'bundle_id' => $bundle->id,
            'email' => strtolower($email),
            'invited_at' => $invited ? now() : null,
        ]);
    }

    private function uploadHeaders(Bundle $bundle): array
    {
        return [
            'X-Upload-Auth' => $bundle->owner_token,
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }

    private function extractOtpFromMail(): string
    {
        $sent = Mail::queued(BundleOtpMail::class)->first();
        $this->assertNotNull($sent);

        return $sent->code;
    }
}
