<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymous uploads (SSO off, IP allowlisted) can now require approval when
 * APPROVAL_REQUIRED_ANONYMOUS is on. Those requests have no requesting user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->foreignId('requested_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Fails if anonymous approval requests exist; remove them first.
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->foreignId('requested_by')->nullable(false)->change();
        });
    }
};
