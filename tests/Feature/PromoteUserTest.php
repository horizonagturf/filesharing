<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PromoteUserTest extends TestCase
{
    public function test_promote_assigns_role_by_username(): void
    {
        $user = User::factory()->create();

        Artisan::call('fs:user:promote', [
            'user' => $user->username,
            '--role' => 'admin',
        ]);

        $user->refresh();
        $this->assertTrue($user->hasRole(UserRole::Admin));
        $this->assertTrue($user->hasRole(UserRole::User));
        $this->assertStringContainsString('admin', Artisan::output());
    }

    public function test_promote_assigns_role_by_email(): void
    {
        $user = User::factory()->create([
            'email' => 'promote@example.com',
        ]);

        Artisan::call('fs:user:promote', [
            'user' => 'promote@example.com',
            '--role' => 'reviewer',
        ]);

        $user->refresh();
        $this->assertTrue($user->hasRole(UserRole::Reviewer));
        $this->assertTrue($user->hasRole(UserRole::User));
    }

    public function test_promote_rejects_invalid_role(): void
    {
        $user = User::factory()->create();

        $exitCode = Artisan::call('fs:user:promote', [
            'user' => $user->username,
            '--role' => 'superadmin',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertFalse($user->fresh()->hasRole(UserRole::Admin));
    }

    public function test_promote_fails_for_unknown_user(): void
    {
        $exitCode = Artisan::call('fs:user:promote', [
            'user' => 'nobody',
            '--role' => 'admin',
        ]);

        $this->assertSame(1, $exitCode);
    }

    public function test_promote_is_noop_when_role_already_assigned(): void
    {
        $user = User::factory()->admin()->create();

        $exitCode = Artisan::call('fs:user:promote', [
            'user' => $user->username,
            '--role' => 'admin',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('already has role', Artisan::output());
    }

    public function test_promote_adds_reviewer_without_removing_admin(): void
    {
        $user = User::factory()->admin()->create();

        Artisan::call('fs:user:promote', [
            'user' => $user->username,
            '--role' => 'reviewer',
        ]);

        $user->refresh();
        $this->assertTrue($user->hasRole(UserRole::Admin));
        $this->assertTrue($user->hasRole(UserRole::Reviewer));
    }

    public function test_promote_fails_when_identifier_matches_multiple_users(): void
    {
        User::factory()->create(['username' => 'alice']);
        User::factory()->create(['email' => 'alice']);

        $exitCode = Artisan::call('fs:user:promote', [
            'user' => 'alice',
            '--role' => 'admin',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('matches multiple users', Artisan::output());
    }
}
