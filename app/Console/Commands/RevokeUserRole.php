<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;

class RevokeUserRole extends Command
{
    protected $signature = 'fs:user:revoke
                            {user? : Username or email address}
                            {--role= : Role to revoke (reviewer, admin)}';

    protected $description = 'Revoke a role from an existing user';

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
            'Select role to revoke',
            ['reviewer', 'admin'],
        )));

        if (! in_array($roleInput, ['reviewer', 'admin'], true)) {
            $this->error('Invalid role. Must be reviewer or admin (the user role cannot be revoked)');

            return self::FAILURE;
        }

        $role = UserRole::from($roleInput);

        if (! $user->hasRole($role)) {
            $this->info('User "'.$user->username.'" does not have role "'.$role->value.'"');

            return self::SUCCESS;
        }

        try {
            $user->revokeRole($role);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'User "%s" revoked role "%s" (roles: %s)',
            $user->username,
            $role->value,
            $user->fresh()->roleSlugs()->implode(', '),
        ));

        return self::SUCCESS;
    }
}
