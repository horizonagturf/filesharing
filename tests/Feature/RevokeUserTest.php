<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RevokeUserTest extends TestCase
{
    public function test_revoke_removes_admin_role(): void
    {
        $user = User::factory()->admin()->create();

        Artisan::call('fs:user:revoke', [
            'user' => $user->username,
            '--role' => 'admin',
        ]);

        $user->refresh();
        $this->assertFalse($user->hasRole(UserRole::Admin));
        $this->assertTrue($user->hasRole(UserRole::User));
    }

    public function test_revoke_refuses_user_role(): void
    {
        $user = User::factory()->create();

        $exitCode = Artisan::call('fs:user:revoke', [
            'user' => $user->username,
            '--role' => 'user',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertTrue($user->fresh()->hasRole(UserRole::User));
    }

    public function test_revoke_is_noop_when_role_not_assigned(): void
    {
        $user = User::factory()->create();

        $exitCode = Artisan::call('fs:user:revoke', [
            'user' => $user->username,
            '--role' => 'admin',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('does not have role', Artisan::output());
    }

    public function test_revoke_fails_when_identifier_matches_multiple_users(): void
    {
        User::factory()->admin()->create(['username' => 'alice']);
        User::factory()->create(['email' => 'alice']);

        $exitCode = Artisan::call('fs:user:revoke', [
            'user' => 'alice',
            '--role' => 'admin',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('matches multiple users', Artisan::output());
    }
}
