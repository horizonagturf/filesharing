<?php

namespace Tests\Feature;

use App\Enums\ApprovalRequestStatus;
use App\Enums\AuditEvent;
use App\Enums\BundleStatus;
use App\Enums\ShareMode;
use App\Filament\Resources\AuditLogResource\Pages\ListAuditLogs;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Bundle;
use App\Models\BundleRecipient;
use App\Models\File;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Livewire\Livewire;
use Mockery;
use SocialiteProviders\Manager\OAuth2\User as AzureSocialiteUser;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['sharing.default_share_mode' => 'invitation']);
    }

    public function test_bundle_created_is_logged(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson('/new', [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEvent::BundleCreated->value,
            'actor_type' => 'user',
            'actor_id' => $user->id,
        ]);
    }

    public function test_submit_for_approval_is_logged(): void
    {
        Mail::fake();
        config(['approval.required_default' => true]);

        $user = User::factory()->create(['requires_approval' => null]);
        User::factory()->reviewer()->create();
        $bundle = $this->createBundle($user, shareMode: ShareMode::StaticLink);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEvent::BundleSubmittedForApproval->value,
            'bundle_id' => $bundle->id,
            'actor_id' => $user->id,
        ]);
    }

    public function test_reviewer_approve_is_logged(): void
    {
        Mail::fake();

        $uploader = User::factory()->create(['requires_approval' => true]);
        $reviewer = User::factory()->reviewer()->create();
        $bundle = $this->createBundle($uploader, shareMode: ShareMode::StaticLink);

        $this->actingAsUser($uploader)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk();

        $request = ApprovalRequest::where('bundle_id', $bundle->id)->firstOrFail();

        $this->actingAsUser($reviewer)
            ->postJson(route('approval.approve', $request), [], [
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEvent::BundleApproved->value,
            'bundle_id' => $bundle->id,
            'actor_id' => $reviewer->id,
        ]);
    }

    public function test_reviewer_deny_is_logged(): void
    {
        Mail::fake();

        $uploader = User::factory()->create(['requires_approval' => true]);
        $reviewer = User::factory()->reviewer()->create();
        $bundle = $this->createBundle($uploader, BundleStatus::PendingApproval, completed: true);
        $request = ApprovalRequest::create([
            'bundle_id' => $bundle->id,
            'requested_by' => $uploader->id,
            'status' => ApprovalRequestStatus::Pending,
        ]);

        $this->actingAsUser($reviewer)
            ->postJson(route('approval.deny', $request), [
                'reason' => 'Policy violation',
            ], [
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEvent::BundleDenied->value,
            'bundle_id' => $bundle->id,
            'actor_id' => $reviewer->id,
        ]);
    }

    public function test_invitation_and_otp_flow_is_logged(): void
    {
        Mail::fake();

        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user);
        $recipient = $this->addRecipient($bundle, 'recipient@example.com');

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEvent::InvitationSent->value,
            'bundle_id' => $bundle->id,
            'recipient_email' => 'recipient@example.com',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEvent::BundleApproved->value,
            'bundle_id' => $bundle->id,
        ]);

        $signedUrl = URL::temporarySignedRoute('invitation.show', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        $this->get($signedUrl)->assertOk();

        $signedOtp = URL::temporarySignedRoute('invitation.otp.request', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        $this->post($signedOtp)->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEvent::OtpRequested->value,
            'recipient_email' => 'recipient@example.com',
        ]);

        $code = Mail::queued(\App\Mail\BundleOtpMail::class)->first()->code;

        $signedVerify = URL::temporarySignedRoute('invitation.otp.verify', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        $this->post($signedVerify, ['code' => $code])
            ->assertRedirect(route('bundle.preview', ['bundle' => $bundle]));

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEvent::OtpVerified->value,
            'recipient_email' => 'recipient@example.com',
        ]);

        $this->get(route('bundle.preview', ['bundle' => $bundle]))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEvent::BundlePreviewed->value,
            'bundle_id' => $bundle->id,
        ]);
    }

    public function test_unverified_recipient_access_is_logged(): void
    {
        $user = User::factory()->create();
        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => 'blocked-'.Str::random(6),
            'owner_token' => substr(sha1('owner'), 0, 15),
            'preview_token' => substr(sha1('preview'), 0, 15),
            'share_mode' => ShareMode::Invitation,
            'completed' => true,
            'status' => BundleStatus::Sent,
            'expiry' => '86400',
            'expires_at' => now()->addDay(),
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $this->get(route('bundle.preview', ['bundle' => $bundle]))
            ->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEvent::AccessDenied->value,
            'bundle_id' => $bundle->id,
        ]);
    }

    public function test_admin_revoke_is_logged(): void
    {
        $admin = User::factory()->admin()->create();
        $bundle = Bundle::create([
            'slug' => 'revoke-'.Str::random(6),
            'owner_token' => substr(sha1('owner'), 0, 15),
            'preview_token' => substr(sha1('preview'), 0, 15),
            'completed' => true,
            'status' => BundleStatus::Approved,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $this->actingAsUser($admin);

        app(\App\Services\BundleAdminService::class)->revoke($bundle);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEvent::AdminBundleRevoked->value,
            'bundle_id' => $bundle->id,
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_sso_login_is_logged(): void
    {
        config([
            'sso.enabled' => true,
            'sso.tenant_id' => 'expected-tenant-id',
            'sso.allowed_domains' => ['yourcompany.com'],
            'services.azure.client_id' => 'test-client-id',
            'services.azure.client_secret' => 'test-client-secret',
            'services.azure.redirect' => 'http://localhost/auth/microsoft/callback',
            'services.azure.tenant' => 'expected-tenant-id',
        ]);

        $this->mockAzureUser('audit.user@yourcompany.com', 'oid-audit');

        $this->get('/auth/microsoft/callback')->assertRedirect(route('homepage'));

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEvent::SsoLogin->value,
        ]);
    }

    public function test_sso_rejection_is_logged(): void
    {
        config([
            'sso.enabled' => true,
            'sso.tenant_id' => 'expected-tenant-id',
            'sso.allowed_domains' => ['yourcompany.com'],
            'services.azure.client_id' => 'test-client-id',
            'services.azure.client_secret' => 'test-client-secret',
            'services.azure.redirect' => 'http://localhost/auth/microsoft/callback',
            'services.azure.tenant' => 'expected-tenant-id',
        ]);

        $this->mockAzureUser('bad@other.com', 'oid-bad');

        $this->get('/auth/microsoft/callback')->assertRedirect(route('login'));

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEvent::SsoRejected->value,
        ]);
    }

    public function test_audit_purge_respects_retention_days(): void
    {
        AuditLog::create([
            'event_type' => AuditEvent::BundleCreated,
            'created_at' => now()->subDays(400),
        ]);

        AuditLog::create([
            'event_type' => AuditEvent::BundleCreated,
            'created_at' => now()->subDay(),
        ]);

        config(['audit.retention_days' => 365]);

        Artisan::call('fs:audit:purge');

        $this->assertSame(1, AuditLog::count());
    }

    public function test_audit_purge_skips_when_retention_is_zero(): void
    {
        AuditLog::create([
            'event_type' => AuditEvent::BundleCreated,
            'created_at' => now()->subYears(5),
        ]);

        config(['audit.retention_days' => 0]);

        Artisan::call('fs:audit:purge');

        $this->assertSame(1, AuditLog::count());
    }

    public function test_audit_export_writes_csv_file(): void
    {
        AuditLog::create([
            'event_type' => AuditEvent::SsoLogin,
            'actor_type' => 'user',
            'created_at' => now(),
        ]);

        Storage::fake('local');

        Artisan::call('fs:audit:export', [
            '--format' => 'csv',
            '--output' => 'audit/test-export.csv',
        ]);

        Storage::disk('local')->assertExists('audit/test-export.csv');
        $this->assertStringContainsString('sso.login', Storage::disk('local')->get('audit/test-export.csv'));
    }

    public function test_admin_can_view_audit_log_table(): void
    {
        $admin = User::factory()->admin()->create();

        AuditLog::create([
            'event_type' => AuditEvent::AccessDenied,
            'created_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(ListAuditLogs::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(AuditLog::all());
    }

    public function test_otp_invalid_code_is_logged(): void
    {
        Mail::fake();

        $bundle = $this->createShareableInvitationBundle();
        $recipient = $this->addRecipient($bundle, 'guest@example.com', invited: true);
        $this->issueOtp($recipient, '123456');

        $signedVerify = URL::temporarySignedRoute('invitation.otp.verify', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        $this->post($signedVerify, ['code' => '000000'])
            ->assertRedirect();

        $log = AuditLog::query()
            ->where('event_type', AuditEvent::OtpFailed->value)
            ->where('recipient_email', 'guest@example.com')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('invalid_code', $log->metadata['reason']);
    }

    public function test_otp_expired_is_logged(): void
    {
        $bundle = $this->createShareableInvitationBundle();
        $recipient = $this->addRecipient($bundle, 'guest@example.com', invited: true);
        $recipient->update([
            'otp_hash' => Hash::make('123456'),
            'otp_expires_at' => now()->subMinute(),
            'otp_attempts' => 0,
        ]);

        $signedVerify = URL::temporarySignedRoute('invitation.otp.verify', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        $this->post($signedVerify, ['code' => '123456'])
            ->assertRedirect();

        $log = AuditLog::query()
            ->where('event_type', AuditEvent::OtpFailed->value)
            ->where('recipient_email', 'guest@example.com')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('expired', $log->metadata['reason']);
    }

    public function test_otp_max_attempts_is_logged(): void
    {
        config(['invitation.otp_max_attempts' => 2]);

        $bundle = $this->createShareableInvitationBundle();
        $recipient = $this->addRecipient($bundle, 'guest@example.com', invited: true);
        $recipient->update([
            'otp_hash' => Hash::make('123456'),
            'otp_expires_at' => now()->addMinutes(15),
            'otp_attempts' => 2,
        ]);

        $signedVerify = URL::temporarySignedRoute('invitation.otp.verify', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        $this->post($signedVerify, ['code' => '123456'])
            ->assertRedirect();

        $log = AuditLog::query()
            ->where('event_type', AuditEvent::OtpFailed->value)
            ->where('recipient_email', 'guest@example.com')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('max_attempts', $log->metadata['reason']);
    }

    public function test_otp_rate_limit_is_logged_as_access_denied(): void
    {
        Mail::fake();
        config(['invitation.otp_rate_limit_per_hour' => 1]);

        $bundle = $this->createShareableInvitationBundle();
        $recipient = $this->addRecipient($bundle, 'guest@example.com', invited: true);

        $signedOtp = URL::temporarySignedRoute('invitation.otp.request', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        $this->post($signedOtp)->assertRedirect();
        $this->postJson($signedOtp, [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(429)
            ->assertJsonPath('message', __('invitation.otp-rate-limited'));

        $log = AuditLog::query()
            ->where('event_type', AuditEvent::AccessDenied->value)
            ->where('recipient_email', 'guest@example.com')
            ->where('metadata->reason', 'otp_rate_limited')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(429, $log->metadata['status']);
    }

    public function test_expired_bundle_access_is_logged(): void
    {
        $bundle = Bundle::create([
            'slug' => 'expired-'.Str::random(6),
            'owner_token' => substr(sha1('owner'), 0, 15),
            'preview_token' => substr(sha1('preview'), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'completed' => true,
            'status' => BundleStatus::Approved,
            'expiry' => '86400',
            'expires_at' => now()->subMinute(),
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $this->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertNotFound();

        $log = AuditLog::query()
            ->where('event_type', AuditEvent::AccessDenied->value)
            ->where('bundle_id', $bundle->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('bundle_expired', $log->metadata['reason']);
    }

    public function test_invalid_static_token_access_is_logged(): void
    {
        $bundle = Bundle::create([
            'slug' => 'static-'.Str::random(6),
            'owner_token' => substr(sha1('owner'), 0, 15),
            'preview_token' => substr(sha1('preview'), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'completed' => true,
            'status' => BundleStatus::Approved,
            'expiry' => '86400',
            'expires_at' => now()->addDay(),
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $this->get("/bundle/{$bundle->slug}/preview?auth=wrong-token")
            ->assertForbidden();

        $log = AuditLog::query()
            ->where('event_type', AuditEvent::AccessDenied->value)
            ->where('bundle_id', $bundle->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('invalid_auth_token', $log->metadata['reason']);
    }

    public function test_max_downloads_exceeded_is_logged(): void
    {
        $bundle = Bundle::create([
            'slug' => 'maxdl-'.Str::random(6),
            'owner_token' => substr(sha1('owner'), 0, 15),
            'preview_token' => substr(sha1('preview'), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'completed' => true,
            'status' => BundleStatus::Approved,
            'expiry' => '86400',
            'expires_at' => now()->addDay(),
            'fullsize' => 0,
            'max_downloads' => 1,
            'downloads' => 1,
        ]);

        $this->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertNotFound();

        $log = AuditLog::query()
            ->where('event_type', AuditEvent::AccessDenied->value)
            ->where('bundle_id', $bundle->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('max_downloads_exceeded', $log->metadata['reason']);
    }

    public function test_zip_download_failure_is_not_logged(): void
    {
        $bundle = Bundle::create([
            'slug' => 'zip-'.Str::random(6),
            'owner_token' => substr(sha1('owner'), 0, 15),
            'preview_token' => substr(sha1('preview'), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'completed' => true,
            'status' => BundleStatus::Approved,
            'expiry' => '86400',
            'expires_at' => now()->addDay(),
            'fullsize' => 100,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'test.txt',
            'filesize' => 100,
            'fullpath' => "{$bundle->slug}/test.txt",
            'filename' => 'test.txt',
            'status' => true,
        ]);

        Storage::shouldReceive('disk')
            ->with('uploads')
            ->andThrow(new \Exception('disk unavailable'));

        $this->get("/bundle/{$bundle->slug}/download?auth={$bundle->preview_token}")
            ->assertStatus(500);

        $this->assertDatabaseMissing('audit_logs', [
            'event_type' => AuditEvent::BundleZipDownloaded->value,
            'bundle_id' => $bundle->id,
        ]);

        $this->assertSame(0, $bundle->fresh()->downloads);
    }

    public function test_audit_export_writes_json_file(): void
    {
        AuditLog::create([
            'event_type' => AuditEvent::SsoLogin,
            'actor_type' => 'user',
            'created_at' => now(),
        ]);

        Storage::fake('local');

        Artisan::call('fs:audit:export', [
            '--format' => 'json',
            '--output' => 'audit/test-export.json',
        ]);

        Storage::disk('local')->assertExists('audit/test-export.json');

        $payload = json_decode(Storage::disk('local')->get('audit/test-export.json'), true);
        $this->assertIsArray($payload);
        $this->assertSame(AuditEvent::SsoLogin->value, $payload[0]['event_type']);
    }

    public function test_audit_export_respects_date_range(): void
    {
        AuditLog::create([
            'event_type' => AuditEvent::BundleCreated,
            'created_at' => now()->subDays(10),
        ]);

        AuditLog::create([
            'event_type' => AuditEvent::BundlePreviewed,
            'created_at' => now()->subDays(2),
        ]);

        Storage::fake('local');

        Artisan::call('fs:audit:export', [
            '--from' => now()->subDays(5)->toDateString(),
            '--to' => now()->toDateString(),
            '--format' => 'json',
            '--output' => 'audit/range-export.json',
        ]);

        $payload = json_decode(Storage::disk('local')->get('audit/range-export.json'), true);
        $this->assertCount(1, $payload);
        $this->assertSame(AuditEvent::BundlePreviewed->value, $payload[0]['event_type']);
    }

    public function test_admin_can_export_audit_log_from_filament(): void
    {
        $admin = User::factory()->admin()->create();

        AuditLog::create([
            'event_type' => AuditEvent::BundleCreated,
            'created_at' => now()->subDay(),
        ]);

        Livewire::actingAs($admin)
            ->test(ListAuditLogs::class)
            ->callAction('export', data: [
                'from' => now()->subWeek()->toDateString(),
                'to' => now()->toDateString(),
                'format' => 'csv',
            ])
            ->assertSuccessful();
    }

    private function createShareableInvitationBundle(): Bundle
    {
        $user = User::factory()->create();

        return $this->createBundle($user, BundleStatus::Sent, completed: true);
    }

    private function issueOtp(BundleRecipient $recipient, string $code): void
    {
        $recipient->update([
            'otp_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(15),
            'otp_attempts' => 0,
        ]);
    }

    private function addRecipient(Bundle $bundle, string $email, bool $invited = false): BundleRecipient
    {
        return BundleRecipient::create([
            'bundle_id' => $bundle->id,
            'email' => strtolower($email),
            'invited_at' => $invited ? now() : null,
        ]);
    }

    private function createBundle(
        User $user,
        BundleStatus $status = BundleStatus::Draft,
        bool $completed = false,
        ShareMode $shareMode = ShareMode::Invitation,
    ): Bundle {
        $slug = 'bundle-'.Str::lower(Str::random(8));

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => 'Test bundle',
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'share_mode' => $shareMode,
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

    private function uploadHeaders(Bundle $bundle): array
    {
        return [
            'X-Upload-Auth' => $bundle->owner_token,
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }

    private function mockAzureUser(string $email, string $oid): void
    {
        $azureUser = new AzureSocialiteUser;
        $azureUser->map([
            'id' => $oid,
            'name' => 'Test User',
            'email' => $email,
        ]);
        $azureUser->setRaw([
            'id' => $oid,
            'displayName' => 'Test User',
            'userPrincipalName' => $email,
            'mail' => $email,
        ]);
        $azureUser->setAccessTokenResponseBody([
            'id_token' => $this->fakeIdToken(['tid' => 'expected-tenant-id']),
        ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($azureUser);
        Socialite::shouldReceive('driver')->with('azure')->andReturn($provider);
    }

    private function fakeIdToken(array $claims): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'none', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');

        return $header.'.'.$payload.'.signature';
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
