<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bundle passwords used to be stored in plain text. The Bundle model now
 * casts them as 'encrypted', so widen the column for ciphertext and encrypt
 * any existing values in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->text('password')->nullable()->change();
        });

        DB::table('bundles')
            ->whereNotNull('password')
            ->where('password', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($bundles) {
                foreach ($bundles as $bundle) {
                    if ($this->isEncrypted($bundle->password)) {
                        continue;
                    }

                    DB::table('bundles')
                        ->where('id', $bundle->id)
                        ->update(['password' => Crypt::encryptString($bundle->password)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('bundles')
            ->whereNotNull('password')
            ->where('password', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($bundles) {
                foreach ($bundles as $bundle) {
                    if (! $this->isEncrypted($bundle->password)) {
                        continue;
                    }

                    DB::table('bundles')
                        ->where('id', $bundle->id)
                        ->update(['password' => Crypt::decryptString($bundle->password)]);
                }
            });

        Schema::table('bundles', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
