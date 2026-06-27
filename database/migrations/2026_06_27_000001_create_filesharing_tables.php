<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('email')->nullable()->index();
            $table->string('password');
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->longText('description')->nullable();
            $table->string('password')->nullable();
            $table->string('owner_token');
            $table->string('preview_token');
            $table->unsignedBigInteger('fullsize')->default(0);
            $table->integer('max_downloads')->nullable();
            $table->integer('downloads')->default(0);
            $table->boolean('completed')->default(false);
            $table->enum('status', [
                'draft',
                'pending_approval',
                'approved',
                'denied',
                'sent',
                'revoked',
            ])->default('draft')->index();
            $table->string('expiry')->default('86400');
            $table->timestamp('expires_at')->nullable();
            $table->string('preview_link')->nullable();
            $table->string('download_link')->nullable();
            $table->string('deletion_link')->nullable();
            $table->timestamps();
        });

        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('bundle_id')->constrained()->cascadeOnDelete();
            $table->string('original')->nullable();
            $table->string('filename')->nullable();
            $table->longText('fullpath')->nullable();
            $table->unsignedBigInteger('filesize')->default(0);
            $table->boolean('status')->default(true);
            $table->string('hash')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('files');
        Schema::dropIfExists('bundles');
        Schema::dropIfExists('users');
    }
};
