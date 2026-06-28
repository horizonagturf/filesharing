<?php

namespace Tests\Feature;

use App\Enums\BundleStatus;
use App\Models\Bundle;
use App\Models\BundleRecipient;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class HelpPagesTest extends TestCase
{
    public function test_help_index_is_public(): void
    {
        $this->get(route('help.index'))
            ->assertOk()
            ->assertSee(__('help.page-title'))
            ->assertSee(__('help.topics.getting-started.title'))
            ->assertSee(__('help.topics.faq.title'));
    }

    public function test_help_topic_page_renders_markdown(): void
    {
        $this->get(route('help.show', ['topic' => 'getting-started']))
            ->assertOk()
            ->assertSee(__('help.topics.getting-started.title'))
            ->assertSee('Sign in with Microsoft', false);
    }

    public function test_unknown_help_topic_returns_not_found(): void
    {
        $this->get(route('help.show', ['topic' => 'not-a-real-topic']))
            ->assertNotFound();
    }

    public function test_footer_includes_help_link(): void
    {
        $this->get(route('help.index'))
            ->assertOk()
            ->assertSee(__('app.nav-help'));
    }

    public function test_guest_sees_help_in_header(): void
    {
        config(['sso.enabled' => false]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(__('app.nav-help'));
    }

    public function test_authenticated_user_sees_help_in_menu(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->get(route('homepage'))
            ->assertOk()
            ->assertSee(__('app.nav-help'));
    }

    public function test_invitation_page_includes_contextual_help_link(): void
    {
        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => 'help-invite-test',
            'title' => 'Help test bundle',
            'owner_token' => substr(sha1('owner'), 0, 15),
            'preview_token' => substr(sha1('preview'), 0, 15),
            'completed' => true,
            'status' => BundleStatus::Sent,
            'require_otp' => true,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $recipient = BundleRecipient::create([
            'bundle_id' => $bundle->id,
            'email' => 'guest@example.com',
            'invited_at' => now(),
        ]);

        $signedShow = URL::temporarySignedRoute('invitation.show', now()->addHour(), [
            'bundle' => $bundle,
            'recipient' => $recipient,
        ]);

        $this->get($signedShow)
            ->assertOk()
            ->assertSee(__('help.learn-more'))
            ->assertSee(route('help.show', ['topic' => 'for-recipients']), false);
    }
}
