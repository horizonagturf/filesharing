<?php

namespace Tests\Unit;

use App\Models\Group;
use App\Models\User;
use App\Services\ApprovalPolicy;
use Tests\TestCase;

class ApprovalPolicyTest extends TestCase
{
    private ApprovalPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ApprovalPolicy;
    }

    public function test_user_override_true_wins_over_group_and_default(): void
    {
        config(['approval.required_default' => false]);

        $user = User::factory()->create(['requires_approval' => true]);
        $group = Group::create([
            'name' => 'No Approval',
            'slug' => 'no-approval',
            'requires_approval' => false,
        ]);
        $user->groups()->attach($group);

        $this->assertTrue($this->policy->requiresApproval($user));
    }

    public function test_user_override_false_wins_over_group_and_default(): void
    {
        config(['approval.required_default' => true]);

        $user = User::factory()->create(['requires_approval' => false]);
        $group = Group::create([
            'name' => 'Approval Required',
            'slug' => 'approval-required',
            'requires_approval' => true,
        ]);
        $user->groups()->attach($group);

        $this->assertFalse($this->policy->requiresApproval($user));
    }

    public function test_group_requires_approval_when_user_override_null(): void
    {
        config(['approval.required_default' => false]);

        $user = User::factory()->create(['requires_approval' => null]);
        $group = Group::create([
            'name' => 'Approval Required',
            'slug' => 'approval-required',
            'requires_approval' => true,
        ]);
        $user->groups()->attach($group);

        $this->assertTrue($this->policy->requiresApproval($user));
    }

    public function test_env_default_applies_when_no_override_or_group_flag(): void
    {
        config(['approval.required_default' => true]);

        $user = User::factory()->create(['requires_approval' => null]);
        $group = Group::create([
            'name' => 'Default',
            'slug' => 'default',
            'requires_approval' => false,
        ]);
        $user->groups()->attach($group);

        $this->assertTrue($this->policy->requiresApproval($user));
    }

    public function test_returns_false_when_no_override_group_or_default(): void
    {
        config(['approval.required_default' => false]);

        $user = User::factory()->create(['requires_approval' => null]);

        $this->assertFalse($this->policy->requiresApproval($user));
    }
}
