<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundle_recipients', function (Blueprint $table) {
            $table->timestamp('revoked_at')->nullable()->after('invited_at');
        });
    }

    public function down(): void
    {
        Schema::table('bundle_recipients', function (Blueprint $table) {
            $table->dropColumn('revoked_at');
        });
    }
};
