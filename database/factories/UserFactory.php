<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'username' => Str::lower(Str::random(8)),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'requires_approval' => null,
            'last_login_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if ($user->roles()->count() === 0) {
                $user->assignRole(UserRole::User);
            }
        });
    }

    public function admin(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole(UserRole::Admin);
        });
    }

    public function reviewer(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole(UserRole::Reviewer);
        });
    }

    /**
     * @param  array<UserRole|string>  $roles
     */
    public function withRoles(array $roles): static
    {
        return $this->afterCreating(function (User $user) use ($roles) {
            $user->syncRoles($roles);
        });
    }
}
