<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;

class PromoteUser extends Command
{
    protected $signature = 'fs:user:promote
                            {user? : Username or email address}
                            {--role=admin : Role to assign (user, reviewer, admin)}';

    protected $description = 'Assign a role to an existing user';

    public function handle(): int
    {
        $identifier = $this->argument('user');

        if (empty($identifier)) {
            $identifier = $this->ask('Enter the user\'s username or email');
        }

        try {
            $user = User::findByUsernameOrEmail($identifier);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($user === null) {
            $this->error('No user found for "'.$identifier.'"');

            return self::FAILURE;
        }

        $roleInput = strtolower((string) ($this->option('role') ?: $this->choice(
            'Select role to assign',
            ['user', 'reviewer', 'admin'],
            'admin',
        )));

        if (! in_array($roleInput, ['user', 'reviewer', 'admin'], true)) {
            $this->error('Invalid role. Must be user, reviewer, or admin');

            return self::FAILURE;
        }

        $role = UserRole::from($roleInput);

        if ($user->hasRole($role)) {
            $this->info('User "'.$user->username.'" already has role "'.$role->value.'"');

            return self::SUCCESS;
        }

        $user->assignRole($role);

        if ($role !== UserRole::User) {
            $user->assignRole(UserRole::User);
        }

        $this->info(sprintf(
            'User "%s" assigned role "%s" (roles: %s)',
            $user->username,
            $role->value,
            $user->fresh()->roleSlugs()->implode(', '),
        ));

        return self::SUCCESS;
    }
}
