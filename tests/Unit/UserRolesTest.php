<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use InvalidArgumentException;
use Tests\TestCase;

class UserRolesTest extends TestCase
{
    public function test_create_with_roles_assigns_roles(): void
    {
        $user = User::createWithRoles([
            'username' => 'createdwithroles',
            'password' => 'secret',
        ], [UserRole::Admin]);

        $this->assertTrue($user->hasRole(UserRole::User));
        $this->assertTrue($user->hasRole(UserRole::Admin));
    }

    public function test_create_with_roles_rolls_back_when_role_assignment_fails(): void
    {
        User::created(function (User $user) {
            if ($user->username === 'rollback01') {
                throw new \RuntimeException('simulated role failure');
            }
        });

        try {
            User::createWithRoles([
                'username' => 'rollback01',
                'password' => 'secret',
            ], [UserRole::User]);
            $this->fail('Expected exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated role failure', $e->getMessage());
        }

        $this->assertDatabaseMissing('users', ['username' => 'rollback01']);
    }

    public function test_assign_role_is_idempotent(): void
    {
        $user = User::factory()->create();

        $user->assignRole(UserRole::Reviewer);
        $user->assignRole(UserRole::Reviewer);

        $this->assertTrue($user->hasRole(UserRole::Reviewer));
        $this->assertSame(2, $user->roles()->count());
    }

    public function test_sync_roles_always_includes_user_role(): void
    {
        $user = User::factory()->create();

        $user->syncRoles([UserRole::Admin]);

        $this->assertTrue($user->hasRole(UserRole::User));
        $this->assertTrue($user->hasRole(UserRole::Admin));
    }

    public function test_revoke_role_throws_for_user_role(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $user->revokeRole(UserRole::User);
    }

    public function test_has_any_role(): void
    {
        $user = User::factory()->withRoles([UserRole::Reviewer])->create();

        $this->assertTrue($user->hasAnyRole(UserRole::Reviewer, UserRole::Admin));
        $this->assertFalse($user->hasAnyRole(UserRole::Admin));
    }

    public function test_find_by_username_or_email_returns_user_by_username(): void
    {
        $user = User::factory()->create(['username' => 'alice']);

        $this->assertTrue($user->is(User::findByUsernameOrEmail('alice')));
    }

    public function test_find_by_username_or_email_returns_user_by_email(): void
    {
        $user = User::factory()->create(['email' => 'alice@example.com']);

        $this->assertTrue($user->is(User::findByUsernameOrEmail('alice@example.com')));
    }

    public function test_find_by_username_or_email_returns_null_when_not_found(): void
    {
        $this->assertNull(User::findByUsernameOrEmail('nobody'));
    }

    public function test_find_by_username_or_email_throws_when_ambiguous(): void
    {
        User::factory()->create(['username' => 'alice']);
        User::factory()->create(['email' => 'alice']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('matches multiple users');

        User::findByUsernameOrEmail('alice');
    }
}
