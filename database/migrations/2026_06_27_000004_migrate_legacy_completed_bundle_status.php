<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bundles')
            ->where('completed', true)
            ->where('status', 'draft')
            ->update(['status' => 'sent']);
    }

    public function down(): void
    {
        // Legacy status cannot be restored reliably.
    }
};
