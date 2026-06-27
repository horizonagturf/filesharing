<?php

namespace Database\Seeders;

use App\Models\Group;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        Group::updateOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Default',
                'requires_approval' => false,
                'allow_static_links' => true,
            ]
        );

        Group::updateOrCreate(
            ['slug' => 'approval-required'],
            [
                'name' => 'Approval Required',
                'requires_approval' => true,
                'allow_static_links' => false,
            ]
        );
    }
}
