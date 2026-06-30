<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\GroupResource;
use App\Filament\Resources\GroupResource\Pages\CreateGroup;
use App\Filament\Resources\GroupResource\Pages\EditGroup;
use App\Filament\Resources\GroupResource\Pages\ListGroups;
use App\Models\Group;
use App\Models\User;
use App\Services\ApprovalPolicy;
use Livewire\Livewire;
use Tests\TestCase;

class AdminGroupResourceTest extends TestCase
{
    public function test_groups_list_exposes_create_action(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue(GroupResource::canCreate());

        Livewire::actingAs($admin)
            ->test(ListGroups::class)
            ->assertTableHeaderActionsExistInOrder(['create']);
    }

    public function test_admin_can_create_group_with_approval_flag(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateGroup::class)
            ->fillForm([
                'name' => 'Compliance',
                'slug' => 'compliance',
                'requires_approval' => true,
                'allow_static_links' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('groups', [
            'slug' => 'compliance',
            'requires_approval' => true,
            'allow_static_links' => false,
        ]);
    }

    public function test_admin_can_assign_members_and_affect_approval_policy(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['requires_approval' => null]);
        $group = Group::create([
            'name' => 'Restricted',
            'slug' => 'restricted',
            'requires_approval' => false,
            'allow_static_links' => true,
        ]);

        $policy = app(ApprovalPolicy::class);
        $this->assertFalse($policy->requiresApproval($user));

        Livewire::actingAs($admin)
            ->test(EditGroup::class, ['record' => $group->getKey()])
            ->fillForm([
                'requires_approval' => true,
                'users' => [$user->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->load('groups');
        $this->assertTrue($policy->requiresApproval($user));
    }
}
