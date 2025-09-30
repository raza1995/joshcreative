<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('sober_days')) {
            return;
        }

        Schema::table('sober_days', function (Blueprint $table) {
            if (!Schema::hasColumn('sober_days', 'mycolean_user_id')) {
                $table->unsignedBigInteger('mycolean_user_id')->nullable()->after('id');
            }
        });

        // Drop old unique index (user_id, date) if present
        try {
            Schema::table('sober_days', function (Blueprint $table) {
                $table->dropUnique('sober_days_user_id_date_unique');
            });
        } catch (\Throwable $e) {
            // ignore if it doesn't exist
        }

        // Add new unique index (mycolean_user_id, date) if not exists
        $exists = false;
        try {
            $exists = collect(DB::select("SHOW INDEX FROM sober_days WHERE Key_name = 'sober_days_myu_date_unique'"))->isNotEmpty();
        } catch (\Throwable $e) { $exists = false; }

        if (!$exists) {
            Schema::table('sober_days', function (Blueprint $table) {
                $table->unique(['mycolean_user_id', 'date'], 'sober_days_myu_date_unique');
            });
        }

        // Add index on date if not present (safe no-op in most cases)
        try {
            Schema::table('sober_days', function (Blueprint $table) {
                $table->index(['date']);
            });
        } catch (\Throwable $e) { /* ignore */ }
    }

    public function down(): void
    {
        if (!Schema::hasTable('sober_days')) return;

        // Drop new unique and column (reversible)
        try {
            Schema::table('sober_days', function (Blueprint $table) {
                $table->dropUnique('sober_days_myu_date_unique');
            });
        } catch (\Throwable $e) { /* ignore */ }

        Schema::table('sober_days', function (Blueprint $table) {
            if (Schema::hasColumn('sober_days', 'mycolean_user_id')) {
                $table->dropColumn('mycolean_user_id');
            }
        });

        // Optionally re-add the old unique (user_id, date) if column still exists
        if (Schema::hasColumn('sober_days', 'user_id')) {
            try {
                Schema::table('sober_days', function (Blueprint $table) {
                    $table->unique(['user_id', 'date']);
                });
            } catch (\Throwable $e) { /* ignore */ }
        }
    }
};

