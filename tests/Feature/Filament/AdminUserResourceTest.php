<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalPolicy;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUserResourceTest extends TestCase
{
    public function test_admin_can_save_approval_override_yes(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['requires_approval' => null]);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['requires_approval' => 'yes'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($user->fresh()->requires_approval);
    }

    public function test_admin_can_save_approval_override_no(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['requires_approval' => null]);
        $group = Group::create([
            'name' => 'Approval Required',
            'slug' => 'approval-required',
            'requires_approval' => true,
            'allow_static_links' => false,
        ]);
        $user->groups()->attach($group);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['requires_approval' => 'no'])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertFalse($user->requires_approval);
        $this->assertFalse(app(ApprovalPolicy::class)->requiresApproval($user));
    }

    public function test_admin_can_save_approval_override_inherit(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['requires_approval' => false]);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['requires_approval' => 'inherit'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($user->fresh()->requires_approval);
    }

    public function test_admin_can_assign_reviewer_role_without_removing_user_role(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm([
                'roles' => [Role::idFor(UserRole::Reviewer)],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertTrue($user->hasRole(UserRole::User));
        $this->assertTrue($user->hasRole(UserRole::Reviewer));
    }

    public function test_admin_can_assign_groups_to_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $group = Group::create([
            'name' => 'Finance',
            'slug' => 'finance',
            'requires_approval' => false,
            'allow_static_links' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['groups' => [$group->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($user->fresh()->groups->contains($group));
    }
}
