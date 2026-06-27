<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginValidationTest extends TestCase
{
    public function test_login_accepts_valid_username(): void
    {
        User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/login', [
            'login' => 'testuser',
            'password' => 'secret123',
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk()
            ->assertJson(['result' => true]);

        $this->assertTrue(Auth::check());
    }

    public function test_login_rejects_too_short_username(): void
    {
        User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/login', [
            'login' => 'abc',
            'password' => 'secret123',
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['login']);
    }
}
