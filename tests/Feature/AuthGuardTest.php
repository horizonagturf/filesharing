<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthGuardTest extends TestCase
{
    public function test_login_authenticates_via_web_guard(): void
    {
        User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/login', [
            'login' => 'testuser',
            'password' => 'secret123',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJson(['result' => true]);

        $this->assertTrue(Auth::check());
        $this->assertSame('testuser', Auth::user()->username);
    }

    public function test_logout_clears_web_guard_session(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('secret123'),
        ]);

        $this->actingAsUser($user);
        $this->assertTrue(Auth::check());

        $this->get('/logout');

        $this->assertFalse(Auth::check());
    }

    public function test_acting_as_user_grants_upload_access_without_ip_bypass(): void
    {
        config(['sharing.upload_ip_limit' => '10.0.0.1']);

        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->get('/')
            ->assertOk();
    }
}
