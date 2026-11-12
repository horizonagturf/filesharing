<?php

namespace Tests\Feature;

use App\Enums\ApprovalRequestStatus;
use App\Enums\BundleStatus;
use App\Enums\ShareMode;
use App\Http\Resources\BundleResource;
use App\Models\ApprovalRequest;
use App\Models\Bundle;
use App\Models\BundleRecipient;
use App\Models\File;
use App\Models\User;
use App\Services\ImageThumbnailService;
use App\Support\SafeMarkdown;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    private const XSS_DESCRIPTION = 'Hello <img src=x onerror=alert(1)> <script>alert(2)</script> [click](javascript:alert(3))';

    // --- Markdown descriptions are rendered without raw HTML -----------------

    public function test_safe_markdown_strips_html_and_unsafe_links(): void
    {
        $html = SafeMarkdown::render(self::XSS_DESCRIPTION.' **bold**');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
    }

    public function test_approval_page_does_not_render_description_html(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $uploader = User::factory()->create();
        $bundle = $this->createBundle($uploader, [
            'status' => BundleStatus::PendingApproval,
            'description' => self::XSS_DESCRIPTION,
        ]);
        $request = ApprovalRequest::create([
            'bundle_id' => $bundle->id,
            'requested_by' => $uploader->id,
            'status' => ApprovalRequestStatus::Pending,
        ]);

        $response = $this->actingAsUser($reviewer)
            ->get(route('approval.show', $request))
            ->assertOk();

        $this->assertStringNotContainsString('<img src="x" onerror', $response->getContent());
        $this->assertStringNotContainsString('<img src=x onerror', $response->getContent());
        $this->assertStringNotContainsString('<script>alert(2)', $response->getContent());
        $this->assertStringNotContainsString('href="javascript:', $response->getContent());
    }

    public function test_guest_preview_description_html_is_sanitized(): void
    {
        $bundle = $this->createBundle(User::factory()->create(), [
            'description' => self::XSS_DESCRIPTION,
        ]);

        $resource = $this->guestResource($bundle);

        $this->assertStringNotContainsString('<img', $resource['description_html']);
        $this->assertStringNotContainsString('<script', $resource['description_html']);
        $this->assertStringNotContainsString('javascript:', $resource['description_html']);
    }

    // --- Bundle identifiers come from a CSPRNG ------------------------------

    public function test_new_bundle_gets_long_random_slug_and_tokens(): void
    {
        $user = User::factory()->create();

        $slugs = [];
        for ($i = 0; $i < 2; $i++) {
            $this->actingAsUser($user)
                ->postJson('/new', [], ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertOk();
        }

        $bundles = Bundle::query()->where('user_id', $user->id)->get();
        $this->assertCount(2, $bundles);

        foreach ($bundles as $bundle) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $bundle->slug);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $bundle->owner_token);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $bundle->preview_token);
            $this->assertNotSame($bundle->owner_token, $bundle->preview_token);
            $slugs[] = $bundle->slug;
        }

        $this->assertNotSame($slugs[0], $slugs[1]);
    }

    // --- Forwarded invitation links still need a code -----------------------

    public function test_previously_verified_recipient_link_requires_otp_in_new_session(): void
    {
        $bundle = $this->createBundle(User::factory()->create(), [
            'share_mode' => ShareMode::Invitation,
            'require_otp' => true,
            'status' => BundleStatus::Sent,
        ]);
        $recipient = BundleRecipient::create([
            'bundle_id' => $bundle->id,
            'email' => 'guest@example.com',
            'invited_at' => now(),
            'verified_at' => now(),
        ]);

        $signedShow = URL::temporarySignedRoute('invitation.show', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        // A fresh browser (e.g. someone the email was forwarded to) sees the
        // code form instead of being let straight in.
        $this->get($signedShow)
            ->assertOk()
            ->assertSee('guest@example.com');

        $this->get("/bundle/{$bundle->slug}/preview")->assertForbidden();
    }

    public function test_recipient_verified_in_this_session_skips_code_form(): void
    {
        $bundle = $this->createBundle(User::factory()->create(), [
            'share_mode' => ShareMode::Invitation,
            'require_otp' => true,
            'status' => BundleStatus::Sent,
        ]);
        $recipient = BundleRecipient::create([
            'bundle_id' => $bundle->id,
            'email' => 'guest@example.com',
            'invited_at' => now(),
            'verified_at' => now(),
        ]);

        $signedShow = URL::temporarySignedRoute('invitation.show', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        $this->withSession(['recipient_access.'.$bundle->id => 'guest@example.com'])
            ->get($signedShow)
            ->assertRedirect(route('bundle.preview', ['bundle' => $bundle]));
    }

    // --- Bundle passwords ---------------------------------------------------

    public function test_bundle_password_is_encrypted_at_rest(): void
    {
        $bundle = $this->createBundle(User::factory()->create(), ['password' => 'hunter22']);

        $raw = DB::table('bundles')->where('id', $bundle->id)->value('password');

        $this->assertNotSame('hunter22', $raw);
        $this->assertSame('hunter22', Crypt::decryptString($raw));
        $this->assertSame('hunter22', $bundle->fresh()->password);
    }

    public function test_locked_bundle_hides_files_and_description_from_guests(): void
    {
        $bundle = $this->createBundle(User::factory()->create(), [
            'password' => 'hunter22',
            'description' => 'Secret plans',
        ]);

        $this->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertOk()
            ->assertDontSee('secret-plans.pdf')
            ->assertDontSee('Secret plans');

        $resource = $this->guestResource($bundle);
        $this->assertSame([], $resource['files']);
        $this->assertNull($resource['description']);
        $this->assertTrue($resource['password_required']);
        $this->assertFalse($resource['password_unlocked']);
    }

    public function test_unlocked_bundle_shows_files_to_guests(): void
    {
        $bundle = $this->createBundle(User::factory()->create(), [
            'password' => 'hunter22',
            'description' => 'Secret plans',
        ]);

        $this->postJson("/bundle/{$bundle->slug}/unlock?auth={$bundle->preview_token}", ['password' => 'wrong'])
            ->assertStatus(422);

        $this->postJson("/bundle/{$bundle->slug}/unlock?auth={$bundle->preview_token}", ['password' => 'hunter22'])
            ->assertOk()
            ->assertJsonPath('result', true);

        $this->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertOk()
            ->assertSee('secret-plans.pdf')
            ->assertSee('Secret plans');
    }

    public function test_thumbnail_requires_password_unlock(): void
    {
        Storage::fake('uploads');

        $bundle = $this->createBundle(User::factory()->create(), ['password' => 'hunter22']);
        $file = $this->storeImageFile($bundle);
        $url = "/bundle/{$bundle->slug}/file/{$file->uuid}/thumbnail?auth={$bundle->preview_token}";

        $this->get($url)->assertForbidden();

        $this->postJson("/bundle/{$bundle->slug}/unlock?auth={$bundle->preview_token}", ['password' => 'hunter22'])
            ->assertOk();

        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    // --- Bundle settings are validated server-side --------------------------

    public function test_store_bundle_rejects_unknown_expiry(): void
    {
        [$user, $bundle] = $this->draftBundle();

        $this->storeSettings($user, $bundle, ['expiry' => '999999999'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('expiry');

        $this->storeSettings($user, $bundle, ['expiry' => 'abc'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('expiry');
    }

    public function test_store_bundle_accepts_configured_expiry_and_forever(): void
    {
        [$user, $bundle] = $this->draftBundle();

        $this->storeSettings($user, $bundle, ['expiry' => '604800'])->assertOk();
        $this->assertSame('604800', $bundle->fresh()->expiry);

        $this->storeSettings($user, $bundle, ['expiry' => 'forever'])->assertOk();
        $this->assertSame('forever', $bundle->fresh()->expiry);
    }

    public function test_store_bundle_rejects_forever_when_disabled(): void
    {
        config(['sharing.allow_forever' => false]);
        [$user, $bundle] = $this->draftBundle();

        $this->storeSettings($user, $bundle, ['expiry' => 'forever'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('expiry');
    }

    public function test_store_bundle_keeps_existing_expiry_when_omitted(): void
    {
        [$user, $bundle] = $this->draftBundle();

        $this->storeSettings($user, $bundle, ['expiry' => null], omitExpiry: true)->assertOk();

        $this->assertSame('86400', $bundle->fresh()->expiry);
    }

    public function test_store_bundle_caps_field_lengths_and_max_downloads(): void
    {
        [$user, $bundle] = $this->draftBundle();

        $this->storeSettings($user, $bundle, ['title' => str_repeat('a', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');

        $this->storeSettings($user, $bundle, ['description' => str_repeat('a', 5001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('description');

        $this->storeSettings($user, $bundle, ['password' => str_repeat('a', 101)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->storeSettings($user, $bundle, ['max_downloads' => 1000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('max_downloads');

        $this->storeSettings($user, $bundle, ['max_downloads' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('max_downloads');
    }

    public function test_store_bundle_treats_empty_password_as_none(): void
    {
        [$user, $bundle] = $this->draftBundle();

        $this->storeSettings($user, $bundle, ['password' => ''])->assertOk();

        $this->assertNull($bundle->fresh()->password);
    }

    // --- Helpers ------------------------------------------------------------

    private function guestResource(Bundle $bundle): array
    {
        $request = Request::create("/bundle/{$bundle->slug}/preview", 'GET');
        $request->attributes->set('bundle_guest_preview', true);
        $request->setLaravelSession(app('session.store'));

        return json_decode(json_encode((new BundleResource($bundle->fresh(['files'])))->toArray($request)), true);
    }

    /**
     * @return array{0: User, 1: Bundle}
     */
    private function draftBundle(): array
    {
        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user, [
            'status' => BundleStatus::Draft,
            'completed' => false,
            'share_mode' => ShareMode::Invitation,
            'require_otp' => false,
            'expires_at' => null,
        ]);

        return [$user, $bundle];
    }

    private function storeSettings(User $user, Bundle $bundle, array $overrides, bool $omitExpiry = false)
    {
        $payload = array_merge([
            'title' => 'Settings test',
            'description' => 'Test',
            'expiry' => '86400',
            'max_downloads' => 0,
            'password' => null,
            'share_mode' => ShareMode::Invitation->value,
            'auth' => $bundle->owner_token,
        ], $overrides);

        if ($omitExpiry) {
            unset($payload['expiry']);
        }

        return $this->actingAsUser($user)->postJson("/upload/{$bundle->slug}", $payload, [
            'X-Upload-Auth' => $bundle->owner_token,
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
    }

    private function createBundle(User $user, array $attributes = []): Bundle
    {
        $slug = 'sec-'.Str::lower(Str::random(8));

        $bundle = Bundle::create(array_merge([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => 'Hardening bundle',
            'owner_token' => Str::lower(Str::random(32)),
            'preview_token' => Str::lower(Str::random(32)),
            'share_mode' => ShareMode::StaticLink,
            'require_otp' => true,
            'completed' => true,
            'status' => BundleStatus::Approved,
            'expiry' => '86400',
            'expires_at' => now()->addDay(),
            'fullsize' => 100,
            'max_downloads' => 0,
            'downloads' => 0,
        ], $attributes));

        File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'secret-plans.pdf',
            'filesize' => 100,
            'fullpath' => "{$slug}/secret-plans.pdf",
            'filename' => 'secret-plans',
            'status' => true,
        ]);

        return $bundle;
    }

    private function storeImageFile(Bundle $bundle): File
    {
        $filename = 'img-'.Str::lower(Str::random(8));
        $fullpath = $bundle->slug.'/'.$filename;

        $image = imagecreatetruecolor(40, 40);
        ob_start();
        imagejpeg($image, null, 90);
        $contents = ob_get_clean();
        imagedestroy($image);

        Storage::disk('uploads')->put($fullpath, $contents);

        $file = File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'photo.jpg',
            'filesize' => strlen($contents),
            'fullpath' => $fullpath,
            'filename' => $filename,
            'status' => true,
        ]);
        $file->setRelation('bundle', $bundle);
        $file->thumbnail_path = app(ImageThumbnailService::class)->generate($file);
        $file->save();

        $this->assertNotNull($file->thumbnail_path);

        return $file;
    }
}
