<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['slug' => 'user', 'name' => 'User'],
            ['slug' => 'reviewer', 'name' => 'Reviewer'],
            ['slug' => 'admin', 'name' => 'Admin'],
        ] as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                ['name' => $role['name']],
            );
        }
    }
}
