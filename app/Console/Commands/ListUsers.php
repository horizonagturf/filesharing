<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListUsers extends Command
{
    protected $signature = 'fs:user:list';

    protected $description = 'Listing of existing users';

    public function handle()
    {
        $users = User::query()
            ->with('roles')
            ->orderBy('username')
            ->get(['id', 'username', 'email', 'last_login_at', 'created_at', 'updated_at']);

        $this->table([
            'username',
            'email',
            'roles',
            'last_login_at',
            'created_at',
            'updated_at',
        ], $users->map(fn (User $user) => [
            'username' => $user->username,
            'email' => $user->email,
            'roles' => $user->roleSlugs()->implode(', '),
            'last_login_at' => $user->last_login_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]));

        return self::SUCCESS;
    }
}
