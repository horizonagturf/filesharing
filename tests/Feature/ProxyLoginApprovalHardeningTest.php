<?php

namespace Tests\Feature;

use App\Enums\ApprovalRequestStatus;
use App\Enums\BundleStatus;
use App\Enums\ShareMode;
use App\Mail\ApprovalRequestSubmittedMail;
use App\Models\ApprovalRequest;
use App\Models\Bundle;
use App\Models\File;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProxyLoginApprovalHardeningTest extends TestCase
{
    // --- Trusted proxies ----------------------------------------------------

    public function test_client_ip_is_read_from_trusted_proxy(): void
    {
        config([
            'trustedproxy.proxies' => '10.0.0.0/24',
            'sso.enabled' => false,
            'sharing.upload_ip_limit' => '203.0.113.5',
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.7'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.5'])
            ->get('/')
            ->assertOk()
            ->assertDontSee(__('app.cannot-upload-blocked-ip'));
    }

    public function test_forwarded_ip_is_ignored_from_untrusted_host(): void
    {
        config([
            'trustedproxy.proxies' => '10.0.0.0/24',
            'sso.enabled' => false,
            'sharing.upload_ip_limit' => '203.0.113.5',
        ]);

        // A client spoofing X-Forwarded-For directly is not a trusted proxy.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.5'])
            ->get('/')
            ->assertSee(__('app.cannot-upload-blocked-ip'));
    }

    public function test_forwarded_ip_is_ignored_when_no_proxies_configured(): void
    {
        config([
            'trustedproxy.proxies' => null,
            'sso.enabled' => false,
            'sharing.upload_ip_limit' => '203.0.113.5',
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.7'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.5'])
            ->get('/')
            ->assertSee(__('app.cannot-upload-blocked-ip'));
    }

    public function test_forwarded_host_is_not_trusted_for_generated_links(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.0/24']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.7'])
            ->withHeaders(['X-Forwarded-Host' => 'evil.example'])
            ->get('/login')
            ->assertOk()
            ->assertDontSee('evil.example');
    }

    // --- Login throttling ---------------------------------------------------

    public function test_repeated_failed_logins_for_one_account_are_throttled(): void
    {
        config(['sso.enabled' => false, 'security.login_rate_limit_per_minute' => 5]);
        User::factory()->create(['username' => 'alice', 'password' => Hash::make('correct-horse')]);

        for ($i = 0; $i < 5; $i++) {
            $this->login('alice', 'wrong-pass')->assertStatus(403);
        }

        $this->login('alice', 'correct-horse')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('result', false);

        // Another account from the same IP is still allowed.
        User::factory()->create(['username' => 'bobby', 'password' => Hash::make('battery-staple')]);
        $this->login('bobby', 'battery-staple')->assertOk()->assertJsonPath('result', true);
    }

    public function test_login_attempts_across_accounts_are_capped_per_ip(): void
    {
        config(['sso.enabled' => false, 'security.login_rate_limit_per_minute' => 2]);

        // 4x the per-account limit = 8 attempts per IP.
        for ($i = 0; $i < 8; $i++) {
            $this->login('user'.$i.'x', 'wrong-pass')->assertStatus(403);
        }

        $this->login('someone', 'wrong-pass')->assertStatus(429);

        // A different client IP is unaffected.
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.44'])
            ->login('someone', 'wrong-pass')
            ->assertStatus(403);
    }

    // --- Approval follows the bundle owner ----------------------------------

    public function test_approval_uses_owner_policy_not_completing_user(): void
    {
        Mail::fake();
        config(['approval.required_default' => false]);

        $owner = User::factory()->create(['requires_approval' => true]);
        $other = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->draftBundle($owner);

        // Someone else holding the owner link completes it while signed in.
        $this->actingAsUser($other)
            ->postJson("/upload/{$bundle->slug}/complete", [], $this->ownerHeaders($bundle))
            ->assertOk()
            ->assertJsonPath('status', BundleStatus::PendingApproval->value);

        $this->assertDatabaseHas('approval_requests', [
            'bundle_id' => $bundle->id,
            'requested_by' => $owner->id,
            'status' => ApprovalRequestStatus::Pending->value,
        ]);
    }

    public function test_owner_without_approval_requirement_is_not_held_by_completing_user(): void
    {
        config(['approval.required_default' => false]);

        $owner = User::factory()->create(['requires_approval' => false]);
        $other = User::factory()->create(['requires_approval' => true]);
        $bundle = $this->draftBundle($owner);

        $this->actingAsUser($other)
            ->postJson("/upload/{$bundle->slug}/complete", [], $this->ownerHeaders($bundle))
            ->assertOk()
            ->assertJsonPath('status', BundleStatus::Approved->value);
    }

    public function test_anonymous_bundle_skips_approval_by_default(): void
    {
        config(['sso.enabled' => false, 'approval.required_default' => true, 'approval.required_anonymous' => false]);

        $bundle = $this->draftBundle(null);

        $this->postJson("/upload/{$bundle->slug}/complete", [], $this->ownerHeaders($bundle))
            ->assertOk()
            ->assertJsonPath('status', BundleStatus::Approved->value);
    }

    public function test_anonymous_bundle_requires_approval_when_configured(): void
    {
        Mail::fake();
        config(['sso.enabled' => false, 'approval.required_anonymous' => true]);

        $reviewer = User::factory()->reviewer()->create(['email' => 'reviewer@example.com']);
        $bundle = $this->draftBundle(null);

        $this->postJson("/upload/{$bundle->slug}/complete", [], $this->ownerHeaders($bundle))
            ->assertOk()
            ->assertJsonPath('status', BundleStatus::PendingApproval->value);

        $request = ApprovalRequest::where('bundle_id', $bundle->id)->firstOrFail();
        $this->assertNull($request->requested_by);
        $this->assertSame(__('approval.anonymous-uploader'), $request->requesterName());

        Mail::assertQueued(ApprovalRequestSubmittedMail::class, function (ApprovalRequestSubmittedMail $mail) {
            $mail->render();

            return $mail->hasTo('reviewer@example.com');
        });

        $this->actingAsUser($reviewer)
            ->get(route('approval.index'))
            ->assertOk()
            ->assertSee(__('approval.anonymous-uploader'));

        $this->actingAsUser($reviewer)
            ->get(route('approval.show', $request))
            ->assertOk()
            ->assertSee(__('approval.anonymous-uploader'));

        $this->actingAsUser($reviewer)
            ->postJson(route('approval.approve', $request), [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertSame(BundleStatus::Approved, $bundle->fresh()->status);
    }

    // --- Helpers ------------------------------------------------------------

    private function login(string $username, string $password)
    {
        return $this->postJson('/login', [
            'login' => $username,
            'password' => $password,
        ], ['X-Requested-With' => 'XMLHttpRequest']);
    }

    private function ownerHeaders(Bundle $bundle): array
    {
        return [
            'X-Upload-Auth' => $bundle->owner_token,
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }

    private function draftBundle(?User $owner): Bundle
    {
        $slug = 'own-'.Str::lower(Str::random(8));

        $bundle = Bundle::create([
            'user_id' => $owner?->id,
            'slug' => $slug,
            'title' => 'Owner policy',
            'owner_token' => Str::lower(Str::random(32)),
            'preview_token' => Str::lower(Str::random(32)),
            'share_mode' => ShareMode::StaticLink,
            'require_otp' => true,
            'completed' => false,
            'status' => BundleStatus::Draft,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'doc.txt',
            'filesize' => 10,
            'fullpath' => "{$slug}/doc.txt",
            'filename' => 'doc',
            'status' => true,
        ]);

        return $bundle;
    }
}
