<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginValidationTest extends TestCase
{
    public function test_login_accepts_valid_alphanumeric_username(): void
    {
        User::create([
            'username' => 'testuser',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/login', [
            'login' => 'testuser',
            'password' => 'secret123',
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk()
            ->assertJson(['result' => true]);
    }

    public function test_login_rejects_invalid_username_characters(): void
    {
        User::create([
            'username' => 'testuser',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/login', [
            'login' => 'test-user',
            'password' => 'secret123',
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['login']);
    }
}
