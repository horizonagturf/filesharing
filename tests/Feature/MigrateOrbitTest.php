<?php

namespace Tests\Feature;

use App\Enums\BundleStatus;
use App\Enums\UserRole;
use App\Models\Bundle;
use App\Models\File;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File as Filesystem;
use Tests\TestCase;

class MigrateOrbitTest extends TestCase
{
    private string $orbitPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orbitPath = storage_path('framework/testing/orbit-'.uniqid());
        Filesystem::ensureDirectoryExists($this->orbitPath.'/users');
        Filesystem::ensureDirectoryExists($this->orbitPath.'/bundles');
        Filesystem::ensureDirectoryExists($this->orbitPath.'/files');
    }

    protected function tearDown(): void
    {
        Filesystem::deleteDirectory($this->orbitPath);
        parent::tearDown();
    }

    public function test_imports_orbit_json_into_sql(): void
    {
        Filesystem::put($this->orbitPath.'/users/alice.json', json_encode([
            'username' => 'alice',
            'password' => '$2y$10$hashedpasswordvalue',
            'connected_at' => null,
        ], JSON_PRETTY_PRINT));

        Filesystem::put($this->orbitPath.'/bundles/testbundle.json', json_encode([
            'slug' => 'testbundle',
            'user_username' => 'alice',
            'owner_token' => 'ownertoken12345',
            'preview_token' => 'previewtoken123',
            'completed' => true,
            'expiry' => 86400,
            'expires_at' => '2026-12-31',
            'fullsize' => 100,
            'downloads' => 2,
            'max_downloads' => 0,
        ], JSON_PRETTY_PRINT));

        Filesystem::put($this->orbitPath.'/files/550e8400-e29b-41d4-a716-446655440000.json', json_encode([
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'bundle_slug' => 'testbundle',
            'original' => 'readme.txt',
            'filename' => 'abc123',
            'fullpath' => 'testbundle/abc123',
            'filesize' => 100,
            'status' => true,
        ], JSON_PRETTY_PRINT));

        Artisan::call('fs:migrate:orbit', ['--path' => $this->orbitPath]);

        $user = User::where('username', 'alice')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->last_login_at);

        $bundle = Bundle::where('slug', 'testbundle')->first();
        $this->assertNotNull($bundle);
        $this->assertSame($user->id, $bundle->user_id);
        $this->assertSame(BundleStatus::Sent, $bundle->status);
        $this->assertTrue($bundle->completed);

        $file = File::where('uuid', '550e8400-e29b-41d4-a716-446655440000')->first();
        $this->assertNotNull($file);
        $this->assertSame($bundle->id, $file->bundle_id);
        $this->assertSame('testbundle/abc123', $file->fullpath);
    }

    public function test_import_is_idempotent(): void
    {
        Filesystem::put($this->orbitPath.'/users/bob.json', json_encode([
            'username' => 'bob',
            'password' => 'hash',
        ]));

        Artisan::call('fs:migrate:orbit', ['--path' => $this->orbitPath]);
        Artisan::call('fs:migrate:orbit', ['--path' => $this->orbitPath]);

        $this->assertSame(1, User::where('username', 'bob')->count());
    }

    public function test_force_import_preserves_existing_sql_role(): void
    {
        User::create([
            'username' => 'charlie',
            'password' => 'existing-hash',
            'role' => UserRole::Reviewer,
        ]);

        Filesystem::put($this->orbitPath.'/users/charlie.json', json_encode([
            'username' => 'charlie',
            'password' => 'orbit-hash',
        ]));

        Artisan::call('fs:migrate:orbit', ['--path' => $this->orbitPath, '--force' => true]);

        $user = User::where('username', 'charlie')->first();
        $this->assertNotNull($user);
        $this->assertSame(UserRole::Reviewer, $user->role);
    }
}
