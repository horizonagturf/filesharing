<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SessionIdleTimeoutTest extends TestCase
{
    public function test_idle_session_is_logged_out_after_timeout(): void
    {
        config(['security.session_idle_timeout' => 60]);

        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->withSession(['last_activity_at' => now()->subMinutes(65)])
            ->get('/account')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('auth.session-expired'));

        $this->assertFalse(Auth::check());
    }

    public function test_active_session_is_not_logged_out(): void
    {
        config(['security.session_idle_timeout' => 60]);

        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->withSession(['last_activity_at' => now()->subMinutes(30)])
            ->get('/account')
            ->assertOk();

        $this->assertTrue(Auth::check());
    }

    public function test_idle_session_json_request_returns_401(): void
    {
        config(['security.session_idle_timeout' => 60]);

        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->withSession(['last_activity_at' => now()->subMinutes(65)])
            ->getJson('/account')
            ->assertUnauthorized()
            ->assertJson(['message' => __('auth.session-expired')]);
    }
}
