<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'role_id']);
        });

        $now = now();
        $roleIds = [];

        foreach ([
            ['slug' => 'user', 'name' => 'User'],
            ['slug' => 'reviewer', 'name' => 'Reviewer'],
            ['slug' => 'admin', 'name' => 'Admin'],
        ] as $role) {
            $roleIds[$role['slug']] = DB::table('roles')->insertGetId([
                'slug' => $role['slug'],
                'name' => $role['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasColumn('users', 'role')) {
            $users = DB::table('users')->get(['id', 'role']);

            foreach ($users as $user) {
                $slug = in_array($user->role, ['user', 'reviewer', 'admin'], true)
                    ? $user->role
                    : 'user';

                $slugsToAttach = array_unique(['user', $slug]);

                foreach ($slugsToAttach as $attachSlug) {
                    DB::table('role_user')->insertOrIgnore([
                        'user_id' => $user->id,
                        'role_id' => $roleIds[$attachSlug],
                    ]);
                }
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['user', 'reviewer', 'admin'])->default('user')->after('password');
        });

        $roleSlugsById = DB::table('roles')->pluck('slug', 'id');
        $users = DB::table('users')->get(['id']);

        foreach ($users as $user) {
            $slugs = DB::table('role_user')
                ->where('user_id', $user->id)
                ->pluck('role_id')
                ->map(fn ($id) => $roleSlugsById[$id] ?? null)
                ->filter()
                ->values()
                ->all();

            $primary = 'user';

            if (in_array('admin', $slugs, true)) {
                $primary = 'admin';
            } elseif (in_array('reviewer', $slugs, true)) {
                $primary = 'reviewer';
            }

            DB::table('users')->where('id', $user->id)->update(['role' => $primary]);
        }

        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
