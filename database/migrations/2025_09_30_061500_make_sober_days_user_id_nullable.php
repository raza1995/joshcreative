<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('sober_days')) return;
        // Make user_id nullable to allow using mycolean_user_id exclusively
        try {
            DB::statement('ALTER TABLE `sober_days` MODIFY `user_id` BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {
            // Best-effort; log in local if needed
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('sober_days')) return;
        // Revert to NOT NULL (may fail if NULLs exist)
        try {
            DB::statement('ALTER TABLE `sober_days` MODIFY `user_id` BIGINT UNSIGNED NOT NULL');
        } catch (\Throwable $e) {
            // ignore
        }
    }
};

