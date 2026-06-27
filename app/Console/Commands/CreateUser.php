<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Throwable;

class CreateUser extends Command
{
    protected $signature = 'fs:user:create
                            {login?}
                            {--role=user : User role (user, reviewer, admin)}';

    protected $description = 'Create a local user account';

    public function handle()
    {
        $login = strtolower($this->argument('login'));

        login:
        if (empty($login)) {
            $login = strtolower($this->ask('Enter the user\'s login'));
        }

        if (! preg_match('~^[a-z0-9]{4,40}$~', $login)) {
            $this->error('Invalid login format. Must only contains letters and numbers, between 4 and 40 chars');
            unset($login);
            goto login;
        }

        $existing = User::where('username', $login)->first();
        if (! empty($existing)) {
            $this->error('User "'.$login.'" already exists');
            unset($login);
            goto login;
        }

        $roleInput = strtolower($this->option('role'));
        if (! in_array($roleInput, ['user', 'reviewer', 'admin'], true)) {
            $this->error('Invalid role. Must be user, reviewer, or admin');

            return self::FAILURE;
        }

        password:
        $password = $this->secret('Enter the user\'s password');

        if (! preg_match('~^[^\s]{5,100}$~', $password)) {
            $this->error('Invalid password format. Must contains between 5 and 100 chars without space');
            unset($password);
            goto password;
        }

        try {
            User::createWithRoles([
                'username' => $login,
                'password' => Hash::make($password),
            ], [UserRole::from($roleInput)]);

            $this->info('User has been created');
        } catch (Throwable $e) {
            $this->error('An error occurred, could not create user');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
