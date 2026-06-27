<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ReviewerPool;
use Tests\TestCase;

class ReviewerPoolTest extends TestCase
{
    public function test_returns_only_reviewer_role_users(): void
    {
        $reviewer = User::factory()->reviewer()->create([
            'username' => 'reviewer1',
            'name' => 'Alice Reviewer',
        ]);
        User::factory()->admin()->create(['username' => 'admin1']);
        User::factory()->create(['username' => 'user1']);

        $pool = ReviewerPool::all();

        $this->assertCount(1, $pool);
        $this->assertTrue($pool->first()->is($reviewer));
    }

    public function test_includes_admin_with_reviewer_role(): void
    {
        $both = User::factory()->withRoles([UserRole::User, UserRole::Admin, UserRole::Reviewer])->create([
            'username' => 'adminreviewer',
        ]);
        User::factory()->admin()->create(['username' => 'adminonly']);

        $pool = ReviewerPool::all();

        $this->assertCount(1, $pool);
        $this->assertTrue($pool->first()->is($both));
    }
}
